<?php

namespace App\Http\Controllers\Services\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Models\PaymentIntent;
use App\Models\Models\Transaction;
use App\Models\Models\Wallet;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * AdminPaymentsController
 *
 * Handles administrative payment operations including logs, reconciliation, and refunds.
 *
 * @package App\Http\Controllers\Services\Payments\Controllers
 */
class AdminPaymentsController extends Controller
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
     * Get paginated transaction logs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 50);
        $status = $request->input('status');
        $walletId = $request->input('wallet_id');

        $query = Transaction::with(['wallet', 'counterpartyWallet'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($walletId) {
            $query->where('wallet_id', $walletId);
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ], 200);
    }

    /**
     * Manually reconcile a payment intent.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function reconcile(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'external_tx' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $intent = PaymentIntent::find($id);

        if (!$intent) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Find associated pending transactions
            $debitTx = Transaction::where('wallet_id', $intent->wallet_id)
                ->where('type', 'debit')
                ->where('status', 'pending')
                ->where('reference', $intent->getReference())
                ->first();

            if ($debitTx) {
                $debitTx->markCompleted($request->external_tx);
            }

            // Mark intent as completed
            $intent->markCompleted();

            // Update metadata with admin notes
            if ($request->notes) {
                $meta = $intent->meta ?? [];
                $meta['admin_notes'] = $request->notes;
                $meta['reconciled_at'] = now()->toIso8601String();
                $intent->meta = $meta;
                $intent->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment intent reconciled successfully.',
                'intent' => $intent,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Reconciliation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund a completed payment.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function refund(int $id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $intent = PaymentIntent::find($id);

        if (!$intent) {
            return response()->json([
                'success' => false,
                'message' => 'Payment intent not found.',
            ], 404);
        }

        if ($intent->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Only completed payments can be refunded.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $wallet = Wallet::find($intent->wallet_id);

            // Credit back to the original wallet
            $wallet->credit($intent->amount);

            // Record refund transaction
            $this->paymentService->recordTransaction(
                $wallet,
                $intent->amount,
                'credit',
                'completed',
                'refund-' . $intent->getReference(),
                'Refund for payment intent #' . $intent->id
            );

            // Update intent metadata
            $meta = $intent->meta ?? [];
            $meta['refunded'] = true;
            $meta['refund_reason'] = $request->reason;
            $meta['refunded_at'] = now()->toIso8601String();
            $intent->meta = $meta;
            $intent->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment refunded successfully.',
                'refund_amount' => $intent->amount,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Refund failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
