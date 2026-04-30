<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** GET /api/payments — list current user's payment history */
    public function index(Request $request)
    {
        $payments = Payment::with('subscription.plan')
            ->where('userId', $request->user()->id)
            ->orderByDesc('paidAt')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $p) => [
                'id'               => $p->id,
                'plan'             => $p->subscription?->plan?->name ?? 'Subscription',
                'amount'           => (float) $p->amount,
                'currency'         => $p->currency,
                // Use Stripe's exact status — webhook stores it raw
                // (paid, failed, pending, refunded, uncollectible, etc.)
                'status'           => $p->status,
                'date'             => $p->paidAt?->format('d M Y') ?? '—',
                'invoice'          => $p->stripeInvoiceId ?? '—',
                'invoicePdfUrl'    => $p->invoicePdfUrl,
                'hostedInvoiceUrl' => $p->hostedInvoiceUrl,
                'description'      => $p->description,
            ]);

        return ApiResponse::success('Payments loaded', ['payments' => $payments]);
    }

    /** GET /api/payments/{id}/invoice — redirect to Stripe-hosted PDF */
    public function invoice(Request $request, $id)
    {
        $payment = Payment::where('id', $id)
            ->where('userId', $request->user()->id)
            ->firstOrFail();

        if (!$payment->invoicePdfUrl) {
            return ApiResponse::error('Invoice not available', [], 404);
        }

        return redirect()->away($payment->invoicePdfUrl);
    }
}