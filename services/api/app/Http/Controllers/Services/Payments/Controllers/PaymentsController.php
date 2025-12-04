<?php

namespace App\Http\Controllers\Services\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ExecutePaymentJob;
use App\Models\Models\PaymentIntent;
use App\Models\Models\Wallet;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * PaymentsController
 *
 * Handles user-facing payment operations for creating and executing payment intents.
 *
 * @package App\Http\Controllers\Services\Payments\Controllers
 */
class PaymentsController extends Controller
{
    /**
     * The payment service instance.
     *
     * @var PaymentService
     */
    protected PaymentService $paymentService;

    /**
     * Create a new controller instance.
     *
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create a new payment intent and reserve funds.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.000000001',
            'currency' => 'required|string|max:10',
            'receiver_address' => 'required|string|max:255',
            'memo' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Get user's wallet
        $wallet = Wallet::where('user_id', $user->id)
            ->where('currency', $request->currency)
            ->where('status', 'active')
            ->first();

        if (!$wallet) {
            return response()->json([
                'success' => false,
                'message' => 'No active wallet found for the specified currency.',
            ], 404);
        }

        // Check sufficient balance
        if (!$wallet->hasSufficientBalance($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance.',
            ], 400);
        }

        // Reserve funds
        try {
            $intent = $this->paymentService->reserveFunds(
                $wallet,
                $request->amount,
                $request->currency,
                $request->receiver_address,
                $request->memo
            );

            return response()->json([
                'success' => true,
                'intent_id' => $intent->id,
                'reference' => $intent->getReference(),
                'status' => $intent->status,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute a payment intent by dispatching the payment job.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function execute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'intent_id' => 'required|integer|exists:payment_intents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $intent = PaymentIntent::find($request->intent_id);

        // Verify ownership
        if ($intent->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: You do not own this payment intent.',
            ], 403);
        }

        // Check if intent can be executed
        if (!$intent->canExecute()) {
            return response()->json([
                'success' => false,
                'message' => "Cannot execute payment intent with status: {$intent->status}",
            ], 400);
        }

        // Mark as ready and dispatch job
        $intent->markReady();
        ExecutePaymentJob::dispatch($intent->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment execution started.',
            'intent_id' => $intent->id,
            'status' => 'ready',
        ], 200);
    }
}
