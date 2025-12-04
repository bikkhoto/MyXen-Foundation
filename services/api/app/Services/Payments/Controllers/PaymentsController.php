<?php

namespace App\Services\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ExecutePayment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentsController extends Controller
{
    /**
     * Create a payment intent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999999',
            'currency' => 'sometimes|string|size:3|in:USD,EUR,GBP',
            'receiver_id' => 'required|integer|exists:users,id',
        ]);

        $sender = $request->user();
        $receiverId = $validated['receiver_id'];

        // Prevent self-payment
        if ($sender->id === $receiverId) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Cannot send payment to yourself.'],
            ]);
        }

        // Check if receiver exists
        $receiver = User::find($receiverId);
        if (!$receiver) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Receiver not found.'],
            ]);
        }

        // Check sender's wallet
        $senderWallet = Wallet::where('user_id', $sender->id)
            ->where('currency', $validated['currency'] ?? 'USD')
            ->first();

        if (!$senderWallet) {
            return response()->json([
                'success' => false,
                'message' => 'Sender wallet not found. Please create a wallet first.',
            ], 404);
        }

        if (!$senderWallet->hasSufficientBalance($validated['amount'])) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance.',
                'data' => [
                    'required' => $validated['amount'],
                    'available' => $senderWallet->balance,
                ],
            ], 400);
        }

        // Create payment intent
        $intent = PaymentIntent::create([
            'sender_id' => $sender->id,
            'receiver_id' => $receiverId,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'USD',
            'reference' => 'PI_' . Str::upper(Str::random(16)),
            'status' => PaymentIntent::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment intent created successfully',
            'data' => [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'sender_id' => $intent->sender_id,
                'receiver_id' => $intent->receiver_id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'status' => $intent->status,
                'created_at' => $intent->created_at,
            ],
        ], 201);
    }

    /**
     * Execute a payment intent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intent_id' => 'required|integer|exists:payment_intents,id',
        ]);

        $intent = PaymentIntent::find($validated['intent_id']);

        // Verify the authenticated user is the sender
        if ($intent->sender_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not the sender of this payment.',
            ], 403);
        }

        // Check if intent is already processing or completed
        if (in_array($intent->status, [
            PaymentIntent::STATUS_PROCESSING,
            PaymentIntent::STATUS_COMPLETED,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent already processed or processing.',
                'data' => [
                    'status' => $intent->status,
                ],
            ], 400);
        }

        // Check if intent has failed or cancelled
        if (in_array($intent->status, [
            PaymentIntent::STATUS_FAILED,
            PaymentIntent::STATUS_CANCELLED,
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot execute failed or cancelled payment intent.',
                'data' => [
                    'status' => $intent->status,
                ],
            ], 400);
        }

        // Mark as processing
        $intent->markAsProcessing();

        // Dispatch job to execute payment
        ExecutePayment::dispatch($intent->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment is being processed',
            'data' => [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'status' => $intent->status,
            ],
        ], 202);
    }

    /**
     * Get payment intent status.
     *
     * @param Request $request
     * @param int $intentId
     * @return JsonResponse
     */
    public function getIntentStatus(Request $request, int $intentId): JsonResponse
    {
        $intent = PaymentIntent::find($intentId);

        if (!$intent) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent not found.',
            ], 404);
        }

        // Verify user has access (sender or receiver)
        if (!in_array($request->user()->id, [$intent->sender_id, $intent->receiver_id])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment intent.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'intent_id' => $intent->id,
                'reference' => $intent->reference,
                'sender_id' => $intent->sender_id,
                'receiver_id' => $intent->receiver_id,
                'amount' => $intent->amount,
                'currency' => $intent->currency,
                'status' => $intent->status,
                'created_at' => $intent->created_at,
                'updated_at' => $intent->updated_at,
            ],
        ], 200);
    }
}
