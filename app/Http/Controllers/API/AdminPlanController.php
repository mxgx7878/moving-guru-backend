<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPlanController extends Controller
{
    public function __construct(protected StripeService $stripe) {}

    /** GET /api/admin/plans */
    public function index()
    {
        $plans = Plan::with('planFeatures')
            ->orderBy('sortOrder')->orderBy('name')->get()
            ->map(function (Plan $p) {
                $p->subscribersCount = Subscription::where('planId', $p->id)
                    ->whereIn('status', ['active', 'trialing', 'past_due'])
                    ->count();
                $p->featureKeys = $p->featureKeys; // accessor
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

            // Optionally enable specific features at create time
            if ($request->has('featureIds')) {
                $plan->planFeatures()->sync($request->input('featureIds', []));
            } else {
                // Default: enable all features for new plans
                $plan->planFeatures()->sync(Feature::pluck('id'));
            }

            return ApiResponse::success('Plan created', ['plan' => $plan->fresh()], 201);
        } catch (\Throwable $e) {
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

    /** DELETE /api/admin/plans/{id} */
    public function destroy(string $id)
    {
        $plan = Plan::find($id);
        if (!$plan) return ApiResponse::error('Plan not found', [], 404);

        $activeCount = Subscription::where('planId', $id)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->count();

        try { $this->stripe->archivePlanInStripe($plan); }
        catch (\Throwable $e) { report($e); }

        $hasAny = Subscription::where('planId', $id)->exists();
        if ($activeCount > 0 || $hasAny) {
            $plan->update(['isActive' => false]);
            return ApiResponse::success(
                $activeCount > 0
                    ? "Plan archived. {$activeCount} active subscriber(s) remain."
                    : 'Plan archived (historical subscriptions reference this plan).',
                ['plan' => $plan->fresh(), 'softDelete' => true]
            );
        }

        $plan->delete();
        return ApiResponse::success('Plan deleted', ['id' => $id, 'softDelete' => false]);
    }

    // ──────────────────────────────────────────────────────────────────
    //  Feature management — works with feature IDs (via sync)
    // ──────────────────────────────────────────────────────────────────

    /** GET /api/admin/plans/{id}/features — enabled feature IDs + keys */
    public function showFeatures(string $id)
    {
        $plan = Plan::with('planFeatures')->find($id);
        if (!$plan) return ApiResponse::error('Plan not found', [], 404);

        return ApiResponse::success('Features loaded', [
            'planId'      => $id,
            'featureIds'  => $plan->planFeatures->pluck('id')->values(),
            'featureKeys' => $plan->planFeatures->pluck('key')->values(),
        ]);
    }

    /**
     * PATCH /api/admin/plans/{id}/features
     * Body: { featureIds: [1, 3, 5, 7] }
     * Replaces enabled features for this plan via Eloquent sync().
     */
    public function updateFeatures(Request $request, string $id)
    {
        $plan = Plan::find($id);
        if (!$plan) return ApiResponse::error('Plan not found', [], 404);

        $validator = Validator::make($request->all(), [
            'featureIds'   => 'present|array',
            'featureIds.*' => 'integer|exists:features,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation failed', $validator->errors(), 422);
        }

        $plan->planFeatures()->sync($request->input('featureIds', []));
        $plan->load('planFeatures');

        return ApiResponse::success('Features updated', [
            'planId'      => $id,
            'featureIds'  => $plan->planFeatures->pluck('id')->values(),
            'featureKeys' => $plan->planFeatures->pluck('key')->values(),
        ]);
    }

    /** POST /api/admin/plans/sync-from-stripe */
    public function syncFromStripe()
    {
        try {
            $result = $this->stripe->syncAllPlansFromStripe();
            return ApiResponse::success(
                "Synced {$result['synced']} plans from Stripe ({$result['skipped']} skipped).",
                $result,
            );
        } catch (\Throwable $e) {
            report($e);
            return ApiResponse::error($e->getMessage(), [], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────────
    //  Shared validation
    // ──────────────────────────────────────────────────────────────────

    protected function validatePayload(Request $request, bool $isCreate): array|\Illuminate\Http\JsonResponse
    {
        $req = $isCreate ? 'required' : 'sometimes';
        $rules = [
            'name'          => "{$req}|string|max:64",
            'description'   => 'nullable|string|max:255',
            'price'         => "{$req}|numeric|min:0",
            'currency'      => 'sometimes|string|size:3',
            'interval'      => "{$req}|in:month,year",
            'intervalCount' => "{$req}|integer|min:1|max:24",
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

        if (empty($data['period']) && (isset($data['interval']) || isset($data['intervalCount']))) {
            $interval = $data['interval']      ?? 'month';
            $count    = $data['intervalCount'] ?? 1;
            $data['period'] = $count > 1
                ? "/{$count}" . substr($interval, 0, 2)
                : '/' . substr($interval, 0, 2);
        }

        return $data;
    }
}