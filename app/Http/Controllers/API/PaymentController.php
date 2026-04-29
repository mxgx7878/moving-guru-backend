<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** GET /api/payments */
    public function index(Request $request)
    {
        $payments = Payment::where('userId', $request->user()->id)
            ->orderByDesc('paidAt')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success('Payments loaded', ['payments' => $payments]);
    }

    /** GET /api/payments/{id}/invoice — redirects to Stripe-hosted PDF */
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