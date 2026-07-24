<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\GrowPromoCode;
use App\Models\GrowPostTier;
use App\Services\GrowPromoCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD + public validation for Grow-only promo codes.
 *
 * Entirely separate from PromoCodeController (subscriptions). No Stripe
 * coupon/promotion objects are created here.
 */
class GrowPromoCodeController extends Controller
{
    public function __construct(protected GrowPromoCodeService $promo) {}

    // ── Admin ──────────────────────────────────────────────────

    /** GET /api/admin/grow-promo-codes */
    public function index()
    {
        $codes = GrowPromoCode::orderByDesc('created_at')->get();

        return ApiResponse::success('Grow promo codes loaded', ['promoCodes' => $codes]);
    }

    /** POST /api/admin/grow-promo-codes */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'           => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'discountType'   => 'required|in:percent,fixed',
            'discountValue'  => 'required|numeric|min:0.01',
            'currency'       => 'required_if:discountType,fixed|nullable|string|size:3',
            'maxRedemptions' => 'nullable|integer|min:1',
            'expiresAt'      => 'nullable|date|after:now',
            'isActive'       => 'sometimes|boolean',
        ], [
            'code.regex' => 'Code can only contain letters, numbers, hyphens and underscores.',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($request->input('discountType') === 'percent' && (float) $request->input('discountValue') > 100) {
                $v->errors()->add('discountValue', 'Percent discount cannot exceed 100.');
            }
        });

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $code = strtoupper(trim($data['code']));

        if (GrowPromoCode::where('code', $code)->exists()) {
            return ApiResponse::error('A Grow promo code with that code already exists.', [], 409);
        }

        $pc = GrowPromoCode::create([
            'code'            => $code,
            'discount_type'   => $data['discountType'],
            'discount_value'  => $data['discountValue'],
            'currency'        => $data['discountType'] === 'fixed' ? strtoupper($data['currency']) : null,
            'max_redemptions' => $data['maxRedemptions'] ?? null,
            'expires_at'      => $data['expiresAt'] ?? null,
            'is_active'       => $data['isActive'] ?? true,
        ]);

        return ApiResponse::success('Grow promo code created', ['promoCode' => $pc->fresh()], 201);
    }

    /** PATCH /api/admin/grow-promo-codes/{id} — toggle active */
    public function update(Request $request, int $id)
    {
        $pc = GrowPromoCode::find($id);
        if (!$pc) return ApiResponse::error('Grow promo code not found', [], 404);

        $validator = Validator::make($request->all(), ['isActive' => 'required|boolean']);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $pc->update(['is_active' => $request->boolean('isActive')]);

        return ApiResponse::success('Grow promo code updated', ['promoCode' => $pc->fresh()]);
    }

    /** DELETE /api/admin/grow-promo-codes/{id} */
    public function destroy(int $id)
    {
        $pc = GrowPromoCode::find($id);
        if (!$pc) return ApiResponse::error('Grow promo code not found', [], 404);

        // Keep a redeemed code on record (deactivate) rather than hard-delete,
        // so historical grow_post_payments keep a valid FK.
        if ($pc->times_redeemed > 0) {
            $pc->update(['is_active' => false]);
            return ApiResponse::success('Grow promo code archived (it has been redeemed).', [
                'promoCode'  => $pc->fresh(),
                'softDelete' => true,
            ]);
        }

        $pc->delete();
        return ApiResponse::success('Grow promo code deleted', ['id' => $id, 'softDelete' => false]);
    }

    // ── Public ─────────────────────────────────────────────────

    /**
     * POST /api/grow-promo-codes/validate  { code, pricingTierId }
     *
     * Public (unauthenticated) — used by both the logged-in checkout and the
     * public Grow form to preview the discounted price. The authoritative
     * discount is re-computed server-side at intent creation; this is only a
     * preview.
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code'          => 'required|string|max:64',
            'pricingTierId' => 'required|integer|exists:grow_post_tiers,id',
        ]);

        try {
            $pc = $this->promo->validate($request->code);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                $e->errors(),
                422,
            );
        }

        $tier = GrowPostTier::active()->findOrFail($request->pricingTierId);

        try {
            $preview = $this->promo->preview($pc, (float) $tier->price, $tier->currency);
        } catch (ValidationException $e) {
            return ApiResponse::error(
                $e->validator->errors()->first('promoCode') ?: 'This code can\'t be applied to this listing.',
                $e->errors(),
                422,
            );
        }

        return ApiResponse::success('Promo code is valid', $preview);
    }
}
