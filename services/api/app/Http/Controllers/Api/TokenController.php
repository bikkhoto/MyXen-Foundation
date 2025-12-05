<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TokenTransaction;
use App\Services\MYXN\MYXNTokenService;
use App\Services\MYXN\ServiceWalletManager;
use App\Services\MYXN\TracingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class TokenController extends Controller
{
    public function __construct(
        protected MYXNTokenService $tokenService,
        protected ServiceWalletManager $walletManager,
        protected TracingService $tracingService
    ) {}

    /**
     * Get token balance for a wallet
     */
    public function balance(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.balance');

        try {
            $validator = Validator::make($request->all(), [
                'wallet_address' => 'required|string|size:44',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $balance = $this->tokenService->getTokenBalance($request->wallet_address);

            $this->tracingService->recordEvent($span, 'balance_fetched', [
                'wallet' => substr($request->wallet_address, 0, 8) . '...',
                'balance' => $balance,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'wallet_address' => $request->wallet_address,
                    'balance' => $balance,
                    'token' => [
                        'symbol' => 'MYXN',
                        'mint' => config('myxn.token.mint'),
                        'decimals' => config('myxn.token.decimals'),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch balance',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Transfer tokens
     */
    public function transfer(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.transfer', [
            'user_id' => $request->user()->id,
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'from_address' => 'required|string|size:44',
                'to_address' => 'required|string|size:44|different:from_address',
                'amount' => 'required|numeric|min:0.000000001',
                'memo' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->tokenService->transfer(
                $request->from_address,
                $request->to_address,
                $request->amount,
                $request->memo
            );

            $this->tracingService->recordTokenTransfer(
                $span,
                $request->from_address,
                $request->to_address,
                $request->amount,
                $result['tx_hash'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Transfer initiated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Transfer failed',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Burn tokens
     */
    public function burn(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.burn', [
            'user_id' => $request->user()->id,
        ]);

        try {
            $validator = Validator::make($request->all(), [
                'from_address' => 'required|string|size:44',
                'amount' => 'required|numeric|min:0.000000001',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->tokenService->burn(
                $request->from_address,
                $request->amount,
                $request->reason
            );

            $this->tracingService->recordEvent($span, 'tokens_burned', [
                'amount' => $request->amount,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tokens burned successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Burn operation failed',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get transaction history
     */
    public function transactions(Request $request): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.transactions', [
            'user_id' => $request->user()->id,
        ]);

        try {
            $query = TokenTransaction::forUser($request->user()->id);

            // Filter by type
            if ($request->has('type')) {
                $query->ofType($request->type);
            }

            // Filter by status
            if ($request->has('status')) {
                $query->withStatus($request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->where('created_at', '<=', $request->to_date);
            }

            $transactions = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get transaction details
     */
    public function transactionDetails(Request $request, int $transactionId): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.transaction_details', [
            'transaction_id' => $transactionId,
        ]);

        try {
            $transaction = TokenTransaction::where('id', $transactionId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            // Add Solscan URL
            $transaction->explorer_url = $transaction->getSolscanUrl();

            return response()->json([
                'success' => true,
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }

    /**
     * Get token information
     */
    public function info(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'name' => 'MyXen Token',
                'symbol' => 'MYXN',
                'mint' => config('myxn.token.mint'),
                'decimals' => config('myxn.token.decimals'),
                'total_supply' => config('myxn.token.total_supply'),
                'network' => config('myxn.network'),
                'platform_fee' => [
                    'enabled' => config('myxn.platform_fee.enabled'),
                    'percentage' => config('myxn.platform_fee.percentage'),
                ],
            ],
        ]);
    }

    /**
     * Get service wallets info (public addresses only)
     */
    public function serviceWallets(): JsonResponse
    {
        $span = $this->tracingService->startSpan('token.service_wallets');

        try {
            $wallets = $this->walletManager->getAllWallets();

            // Only expose public addresses and purposes
            $publicWallets = collect($wallets)->map(function ($wallet) {
                return [
                    'type' => $wallet['type'],
                    'address' => $wallet['address'],
                    'purpose' => $wallet['purpose'],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $publicWallets,
            ]);
        } catch (\Exception $e) {
            $this->tracingService->setSpanStatus($span, 'error', $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service wallets',
                'error' => $e->getMessage(),
            ], 500);
        } finally {
            $this->tracingService->endSpan($span);
        }
    }
}
