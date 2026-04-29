<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPlanController extends Controller
{
    public function __construct(protected StripeService $stripe) {}

    /** GET /api/admin/plans — list all plans (active + inactive) with subscriber counts */
    public function index()
    {
        $plans = Plan::orderBy('sortOrder')->orderBy('name')->get()
            ->map(function (Plan $p) {
                $p->subscribersCount = Subscription::where('planId', $p->id)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->count();
                return $p;
            });

        return ApiResponse::success('Plans loaded', ['plans' => $plans]);
    }

    /** POST /api/admin/plans */
    public function store(Request $request)
    {
        $data = $this->validatePayload($request, isCreate: true);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        if (Plan::where('id', $data['id'])->exists()) {
            return ApiResponse::error('A plan with that ID already exists.', [], 409);
        }

        try {
            $plan = Plan::create($data);
            $this->stripe->createPlanInStripe($plan);
            return ApiResponse::success('Plan created', ['plan' => $plan->fresh()], 201);
        } catch (\Throwable $e) {
            // If Stripe failed AFTER the DB row was created, roll back.
            if (isset($plan)) $plan->delete();
            report($e);
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /** PATCH /api/admin/plans/{id} */
    public function update(Request $request, string $id)
    {
        $plan = Plan::find($id);
        if (!$plan) return ApiResponse::error('Plan not found', [], 404);

        $data = $this->validatePayload($request, isCreate: false);
        if ($data instanceof \Illuminate\Http\JsonResponse) return $data;

        try {
            $plan->update($data);
            $this->stripe->syncPlanToStripe($plan);
            return ApiResponse::success('Plan updated', ['plan' => $plan->fresh()]);
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    /**
     * DELETE /api/admin/plans/{id}
     * Soft delete — archives in Stripe and flips isActive=false locally.
     * Hard delete is blocked when there are active subscribers.
     */
    public function destroy(string $id)
    {
        $plan = Plan::find($id);
        if (!$plan) return ApiResponse::error('Plan not found', [], 404);

        $activeCount = Subscription::where('planId', $id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->count();

        try {
            $this->stripe->archivePlanInStripe($plan);
        } catch (\Throwable $e) {
            report($e);
            // Don't block the local archive on Stripe errors
        }

        if ($activeCount > 0) {
            $plan->update(['isActive' => false]);
            return ApiResponse::success(
                "Plan archived. {$activeCount} active subscriber(s) remain on this plan until they cancel or switch.",
                ['plan' => $plan->fresh(), 'softDelete' => true]
            );
        }

        // No active subs — but historical subscriptions reference planId.
        // Keep the row to preserve FK integrity.
        $hasAny = Subscription::where('planId', $id)->exists();
        if ($hasAny) {
            $plan->update(['isActive' => false]);
            return ApiResponse::success('Plan archived (historical subscriptions reference this plan).', [
                'plan' => $plan->fresh(), 'softDelete' => true,
            ]);
        }

        $plan->delete();
        return ApiResponse::success('Plan deleted', ['id' => $id, 'softDelete' => false]);
    }

    // ─────────────────────────────────────────────────────────────

    protected function validatePayload(Request $request, bool $isCreate): array|\Illuminate\Http\JsonResponse
    {
        $rules = [
            'name'          => ($isCreate ? 'required' : 'sometimes') . '|string|max:64',
            'description'   => 'nullable|string|max:255',
            'price'         => ($isCreate ? 'required' : 'sometimes') . '|numeric|min:0',
            'currency'      => 'sometimes|string|size:3',
            'interval'      => ($isCreate ? 'required' : 'sometimes') . '|in:month,year',
            'intervalCount' => ($isCreate ? 'required' : 'sometimes') . '|integer|min:1|max:24',
            'period'        => 'sometimes|nullable|string|max:16',
            'features'      => 'sometimes|array',
            'features.*'    => 'string|max:140',
            'isFeatured'    => 'sometimes|boolean',
            'isActive'      => 'sometimes|boolean',
            'sortOrder'     => 'sometimes|integer|min:0|max:9999',
        ];

        if ($isCreate) {
            $rules['id'] = 'required|string|max:32|regex:/^[a-z0-9][a-z0-9_-]*$/';
        }

        $validator = Validator::make($request->all(), $rules, [
            'id.regex' => 'ID must be lowercase letters, numbers, hyphens or underscores.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Auto-derive `period` if the admin didn't provide one
        if (empty($data['period']) && (isset($data['interval']) || isset($data['intervalCount']))) {
            $interval = $data['interval']      ?? 'month';
            $count    = $data['intervalCount'] ?? 1;
            $data['period'] = $count > 1 ? "/{$count}" . substr($interval, 0, 2)
                                          : '/' . substr($interval, 0, 2);
        }

        return $data;
    }
}