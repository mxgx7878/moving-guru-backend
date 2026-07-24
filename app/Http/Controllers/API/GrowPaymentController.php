<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GrowPost;
use App\Models\GrowPostPayment;
use App\Models\GrowPostTier;
use App\Models\GrowPromoCode;
use App\Services\GrowPromoCodeService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Stripe\StripeClient;

class GrowPaymentController extends Controller
{
    private StripeClient $stripe;

    private const BOOST = ['amount' => 1000, 'days' => 7];

    public function __construct(
        private StripeService $stripeService,
        private GrowPromoCodeService $growPromo,
    ) {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'purpose' => ['required', Rule::in(['listing', 'boost'])],
            'pricingTierId' => ['required_if:purpose,listing', 'nullable', 'integer', 'exists:grow_post_tiers,id'],
            'postId' => ['required_if:purpose,boost', 'nullable', 'integer'],
            'paymentMethodId' => ['required', 'string', 'starts_with:pm_'],
            // Promo codes apply to LISTING fees only (boosts stay full price).
            'promoCode' => ['nullable', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $purpose = $validated['purpose'];
        $post = null;

        if ($purpose === 'boost') {
            $post = GrowPost::where('user_id', $user->id)
                ->where('status', 'approved')
                ->findOrFail($validated['postId']);

            if ($post->is_featured && $post->boost_until?->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This post is already boosted.',
                ], 422);
            }
        }

        $tier = $purpose === 'listing'
            ? GrowPostTier::active()->findOrFail($validated['pricingTierId'])
            : null;
        $config = $tier
            ? ['amount' => (int) round(((float) $tier->price) * 100), 'days' => $tier->duration_days]
            : self::BOOST;
        $currency = strtolower($tier?->currency ?? config('services.stripe.currency', 'aud'));

        // ── Promo code (listing only) ──────────────────────────
        $originalCents = $config['amount'];
        $chargeCents   = $originalCents;
        $discountCents = 0;
        $promo         = null;

        if ($purpose === 'listing' && !empty($validated['promoCode'])) {
            try {
                $promo = $this->growPromo->validate($validated['promoCode']);
                [$chargeCents, $discountCents] = $this->growPromo->applyToCents($promo, $originalCents);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                ], 422);
            }
        }

        $customerId = $this->stripeService->getOrCreateCustomer($user);
        $paymentMethod = $this->stripe->paymentMethods->retrieve($validated['paymentMethodId']);
        $paymentMethodCustomer = is_string($paymentMethod->customer)
            ? $paymentMethod->customer
            : ($paymentMethod->customer->id ?? null);
        if ($paymentMethodCustomer !== $customerId) {
            abort(422, 'The selected card does not belong to your account.');
        }

        $intent = $this->stripe->paymentIntents->create([
            'amount' => $chargeCents,
            'currency' => $currency,
            'customer' => $customerId,
            'payment_method' => $validated['paymentMethodId'],
            'payment_method_types' => ['card'],
            // Listing fees and boosts are both charged immediately.
            'capture_method' => 'automatic',
            'setup_future_usage' => 'off_session',
            'description' => $purpose === 'listing'
                ? 'Grow listing payment'
                : "Grow post boost #{$post->id}",
            'metadata' => array_filter([
                'userId' => (string) $user->id,
                'purpose' => $purpose,
                'pricingTierId' => isset($validated['pricingTierId']) ? (string) $validated['pricingTierId'] : null,
                'postId' => $post ? (string) $post->id : null,
                'growPromoCode' => $promo?->code,
                'growPromoCodeId' => $promo ? (string) $promo->id : null,
            ]),
        ]);

        GrowPostPayment::create([
            'user_id' => $user->id,
            'grow_post_id' => $post?->id,
            'purpose' => $purpose,
            'pricing_tier' => isset($validated['pricingTierId']) ? (string) $validated['pricingTierId'] : null,
            'grow_promo_code_id' => $promo?->id,
            'promo_code' => $promo?->code,
            'duration_days' => $config['days'],
            'amount' => $chargeCents / 100,
            'original_amount' => $originalCents / 100,
            'discount_amount' => $discountCents / 100,
            'currency' => strtoupper($currency),
            'stripe_payment_intent_id' => $intent->id,
            'status' => 'requires_confirmation',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'paymentIntentId' => $intent->id,
                'clientSecret' => $intent->client_secret,
                'expectedStatus' => 'succeeded',
            ],
        ]);
    }

    public function completeIntent(Request $request)
    {
        $validated = $request->validate([
            'paymentIntentId' => ['required', 'string', 'starts_with:pi_'],
        ]);

        $payment = GrowPostPayment::where('user_id', $request->user()->id)
            ->where('stripe_payment_intent_id', $validated['paymentIntentId'])
            ->firstOrFail();
        $intent = $this->stripe->paymentIntents->retrieve($validated['paymentIntentId']);

        if ((string) ($intent->metadata->userId ?? '') !== (string) $request->user()->id) {
            abort(403, 'This payment does not belong to you.');
        }

        if ($intent->status !== 'succeeded') {
            return response()->json(['success' => false, 'message' => 'Payment was not completed.'], 422);
        }

        if ($payment->purpose === 'listing') {
            $this->markListingSucceeded($payment);
        } else {

            DB::transaction(function () use ($payment) {
                $post = GrowPost::where('user_id', $payment->user_id)
                    ->lockForUpdate()
                    ->findOrFail($payment->grow_post_id);
                $post->update([
                    'is_featured' => true,
                    'boost_until' => now()->addDays($payment->duration_days),
                ]);
                $payment->update(['status' => 'succeeded', 'captured_at' => now()]);
            });
        }

        return response()->json([
            'success' => true,
            'message' => $payment->purpose === 'listing'
                ? 'Payment successful. Submit your post for review.'
                : 'Payment successful. Your post is now boosted.',
            'data' => [
                'paymentIntentId' => $payment->stripe_payment_intent_id,
                'post' => $payment->purpose === 'boost'
                    ? $payment->growPost()->with('user')->first()
                    : null,
            ],
        ]);
    }

    /** Create an immediate card payment for the public Grow form. */
    public function createPublicIntent(Request $request)
    {
        $validated = $request->validate([
            'contactName' => ['required', 'string', 'max:120'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'pricingTierId' => ['required', 'integer', 'exists:grow_post_tiers,id'],
            'promoCode' => ['nullable', 'string', 'max:64'],
        ]);

        $tier = GrowPostTier::active()->findOrFail($validated['pricingTierId']);

        // ── Promo code ─────────────────────────────────────────
        $originalCents = (int) round(((float) $tier->price) * 100);
        $chargeCents   = $originalCents;
        $discountCents = 0;
        $promo         = null;

        if (!empty($validated['promoCode'])) {
            try {
                $promo = $this->growPromo->validate($validated['promoCode']);
                [$chargeCents, $discountCents] = $this->growPromo->applyToCents($promo, $originalCents);
            } catch (ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('promoCode') ?: 'Invalid promo code.',
                ], 422);
            }
        }

        $token = Str::random(64);
        $customer = $this->stripe->customers->create([
            'name' => $validated['contactName'],
            'email' => $validated['contactEmail'],
            'metadata' => ['source' => 'public_grow'],
        ]);
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $chargeCents,
            'currency' => strtolower($tier->currency),
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'capture_method' => 'automatic',
            'description' => 'Public Grow listing payment',
            'metadata' => array_filter([
                'source' => 'public_grow',
                'contactEmail' => strtolower($validated['contactEmail']),
                'pricingTierId' => (string) $tier->id,
                'growPromoCode' => $promo?->code,
                'growPromoCodeId' => $promo ? (string) $promo->id : null,
            ]),
        ]);

        GrowPostPayment::create([
            'user_id' => null,
            'contact_email' => strtolower($validated['contactEmail']),
            'stripe_customer_id' => $customer->id,
            'public_token_hash' => hash('sha256', $token),
            'purpose' => 'listing',
            'pricing_tier' => (string) $tier->id,
            'grow_promo_code_id' => $promo?->id,
            'promo_code' => $promo?->code,
            'duration_days' => $tier->duration_days,
            'amount' => $chargeCents / 100,
            'original_amount' => $originalCents / 100,
            'discount_amount' => $discountCents / 100,
            'currency' => strtoupper($tier->currency),
            'stripe_payment_intent_id' => $intent->id,
            'status' => 'requires_confirmation',
        ]);

        return response()->json(['success' => true, 'data' => [
            'paymentIntentId' => $intent->id,
            'clientSecret' => $intent->client_secret,
            'submissionToken' => $token,
            // A Stripe publishable key is safe to expose to browser clients.
            'publishableKey' => config('services.stripe.key'),
        ]]);
    }

    public function completePublicIntent(Request $request)
    {
        $validated = $request->validate([
            'paymentIntentId' => ['required', 'string', 'starts_with:pi_'],
            'submissionToken' => ['required', 'string', 'size:64'],
        ]);
        $payment = GrowPostPayment::where('stripe_payment_intent_id', $validated['paymentIntentId'])
            ->where('public_token_hash', hash('sha256', $validated['submissionToken']))
            ->whereNull('user_id')
            ->firstOrFail();
        $intent = $this->stripe->paymentIntents->retrieve($payment->stripe_payment_intent_id);
        if ($intent->status !== 'succeeded') {
            return response()->json(['success' => false, 'message' => 'Payment was not completed.'], 422);
        }
        $this->markListingSucceeded($payment);
        return response()->json(['success' => true, 'data' => [
            'paymentIntentId' => $payment->stripe_payment_intent_id,
            'submissionToken' => $validated['submissionToken'],
        ]]);
    }

    /**
     * Mark a listing payment succeeded and, the first time it transitions,
     * bump the promo code's redemption counter (global cap accounting).
     * Idempotent — safe to call more than once for the same payment.
     */
    private function markListingSucceeded(GrowPostPayment $payment): void
    {
        if ($payment->status === 'succeeded') {
            return;
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['status' => 'succeeded', 'captured_at' => now()]);

            if ($payment->grow_promo_code_id) {
                GrowPromoCode::whereKey($payment->grow_promo_code_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('times_redeemed');
            }
        });
    }
}
