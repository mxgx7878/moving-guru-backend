<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PromoCodeController extends Controller
{
    public function __construct(
        protected StripeService $stripe,
        protected PromoCodeService $promo,
    ) {}

    // ── Admin ──────────────────────────────────────────────────

    /** GET /api/admin/promo-codes */
    public function index()
    {
        $codes = PromoCode::withCount('redemptions')
            ->orderByDesc('created_at')->get();

        return ApiResponse::success('Promo codes loaded', ['promoCodes' => $codes]);
    }

    /** POST /api/admin/promo-codes */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code'             => 'required|string|max:64|regex:/^[A-Za-z0-9_-]+$/',
            'discountType'     => 'required|in:percent,fixed',
            'discountValue'    => 'required|numeric|min:0.01',
            'currency'         => 'required_if:discountType,fixed|nullable|string|size:3',
            'duration'         => 'required|in:once,repeating,forever',
            'durationInMonths' => 'required_if:duration,repeating|nullable|integer|min:1|max:36',
            'maxRedemptions'   => 'nullable|integer|min:1',
            'expiresAt'        => 'nullable|date|after:now',
            'isActive'         => 'sometimes|boolean',
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
        $data['code']     = strtoupper(trim($data['code']));
        $data['currency'] = $data['discountType'] === 'fixed' ? strtoupper($data['currency']) : null;
        $data['isActive'] = $data['isActive'] ?? true;
        if ($data['duration'] !== 'repeating') $data['durationInMonths'] = null;

        if (PromoCode::where('code', $data['code'])->exists()) {
            return ApiResponse::error('A promo code with that code already exists.', [], 409);
        }

        try {
            $pc = PromoCode::create($data);
            $this->stripe->createPromoCodeInStripe($pc);
            return ApiResponse::success('Promo code created', ['promoCode' => $pc->fresh()], 201);
        } catch (\Throwable $e) {
            if (isset($pc)) $pc->delete();
            report($e);
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * PATCH /api/admin/promo-codes/{id}
     * Only the active toggle is editable — Stripe coupons/promo codes are
     * immutable. To change a discount, deactivate and create a new code.
     */
    public function update(Request $request, int $id)
    {
        $pc = PromoCode::find($id);
        if (!$pc) return ApiResponse::error('Promo code not found', [], 404);

        $validator = Validator::make($request->all(), ['isActive' => 'required|boolean']);
        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $pc->update(['isActive' => $request->boolean('isActive')]);
        $this->stripe->setPromoCodeActiveInStripe($pc);

        return ApiResponse::success('Promo code updated', [
            'promoCode' => $pc->fresh()->loadCount('redemptions'),
        ]);
    }

    /** DELETE /api/admin/promo-codes/{id} */
    public function destroy(int $id)
    {
        $pc = PromoCode::find($id);
        if (!$pc) return ApiResponse::error('Promo code not found', [], 404);

        try { $this->stripe->archivePromoCodeInStripe($pc); }
        catch (\Throwable $e) { report($e); }

        if ($pc->redemptions()->exists()) {
            $pc->update(['isActive' => false]);
            return ApiResponse::success('Promo code archived (it has redemptions on record).', [
                'promoCode'  => $pc->fresh()->loadCount('redemptions'),
                'softDelete' => true,
            ]);
        }

        $pc->delete();
        return ApiResponse::success('Promo code deleted', ['id' => $id, 'softDelete' => false]);
    }

    // ── User ───────────────────────────────────────────────────

    /** POST /api/promo-codes/validate  { code, planId? } */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code'   => 'required|string|max:64',
            'planId' => 'nullable|string|exists:plans,id',
        ]);

        try {
            $pc = $this->promo->validateForUser($request->user(), $request->code);
        } catch (ValidationException $e) {
            log::info('Promo code validation failed', [
                'userId' => $request->user()->id,
                'code'   => $request->code,
                'reason' => $e->validator->errors()->first('promoCode'),
            ]);
            return ApiResponse::error(
                $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                $e->errors(),
                422,
            );
        }

        $payload = [
            'code'          => $pc->code,
            'discountType'  => $pc->discountType,
            'discountValue' => (float) $pc->discountValue,
            'duration'      => $pc->duration,
        ];

        if ($request->planId && ($plan = Plan::find($request->planId))) {
            $payload['preview'] = $this->promo->preview($pc, $plan);
        }

        return ApiResponse::success('Promo code is valid', $payload);
    }
}