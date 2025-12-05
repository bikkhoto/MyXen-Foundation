<?php

namespace App\Services\MYXN;

use App\Services\Payments\SolanaWorkerClient;
use App\Services\MYXN\ServiceWalletManager;
use App\Services\MYXN\TracingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * MYXNTokenService
 *
 * Comprehensive service for MYXN token operations including transfers,
 * burns, and integration with financial programs.
 *
 * @package App\Services\MYXN
 */
class MYXNTokenService
{
    /**
     * Token configuration.
     *
     * @var array
     */
    protected array $tokenConfig;

    /**
     * Solana worker client for blockchain operations.
     *
     * @var SolanaWorkerClient
     */
    protected SolanaWorkerClient $solanaClient;

    /**
     * Service wallet manager.
     *
     * @var ServiceWalletManager
     */
    protected ServiceWalletManager $walletManager;

    /**
     * Tracing service.
     *
     * @var TracingService
     */
    protected TracingService $tracer;

    /**
     * Create a new MYXN token service instance.
     */
    public function __construct(
        SolanaWorkerClient $solanaClient,
        ServiceWalletManager $walletManager,
        TracingService $tracer
    ) {
        $this->tokenConfig = config('myxn.token', []);
        $this->solanaClient = $solanaClient;
        $this->walletManager = $walletManager;
        $this->tracer = $tracer;
    }

    /**
     * Get the MYXN token mint address.
     *
     * @return string
     */
    public function getTokenMint(): string
    {
        return $this->tokenConfig['mint'];
    }

    /**
     * Get token decimals.
     *
     * @return int
     */
    public function getDecimals(): int
    {
        return $this->tokenConfig['decimals'] ?? 9;
    }

    /**
     * Convert human-readable amount to raw token amount.
     *
     * @param float $amount
     * @return string
     */
    public function toRawAmount(float $amount): string
    {
        $multiplier = pow(10, $this->getDecimals());
        return bcmul((string)$amount, (string)$multiplier, 0);
    }

    /**
     * Convert raw token amount to human-readable amount.
     *
     * @param string $rawAmount
     * @return float
     */
    public function fromRawAmount(string $rawAmount): float
    {
        $divisor = pow(10, $this->getDecimals());
        return (float)bcdiv($rawAmount, (string)$divisor, $this->getDecimals());
    }

    /**
     * Transfer MYXN tokens between wallets.
     *
     * @param string $fromTokenAccount
     * @param string $toTokenAccount
     * @param float $amount
     * @param string $purpose
     * @param array $metadata
     * @return array
     */
    public function transfer(
        string $fromTokenAccount,
        string $toTokenAccount,
        float $amount,
        string $purpose = 'transfer',
        array $metadata = []
    ): array {
        $span = $this->tracer->startSpan('myxn.token.transfer', [
            'from_account' => $fromTokenAccount,
            'to_account' => $toTokenAccount,
            'amount' => $amount,
            'purpose' => $purpose,
        ]);

        try {
            $requestId = $this->generateRequestId($purpose);
            $rawAmount = $this->toRawAmount($amount);

            Log::channel('myxn')->info('Initiating MYXN transfer', [
                'request_id' => $requestId,
                'from' => $fromTokenAccount,
                'to' => $toTokenAccount,
                'amount' => $amount,
                'raw_amount' => $rawAmount,
                'purpose' => $purpose,
                'metadata' => $metadata,
            ]);

            $result = $this->solanaClient->transfer(
                $this->getTokenMint(),
                $rawAmount,
                $fromTokenAccount,
                $toTokenAccount,
                $requestId
            );

            if ($result['success']) {
                $this->tracer->addEvent($span, 'transfer_completed', [
                    'tx_signature' => $result['txSignature'],
                ]);

                Log::channel('myxn')->info('MYXN transfer completed', [
                    'request_id' => $requestId,
                    'tx_signature' => $result['txSignature'],
                ]);

                return [
                    'success' => true,
                    'tx_signature' => $result['txSignature'],
                    'amount' => $amount,
                    'request_id' => $requestId,
                ];
            }

            throw new \Exception($result['error'] ?? 'Transfer failed');
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            Log::channel('myxn')->error('MYXN transfer failed', [
                'error' => $e->getMessage(),
                'from' => $fromTokenAccount,
                'to' => $toTokenAccount,
                'amount' => $amount,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Transfer tokens to a service wallet.
     *
     * @param string $fromTokenAccount
     * @param string $serviceWalletType treasury|burn|charity|hr|marketing
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function transferToServiceWallet(
        string $fromTokenAccount,
        string $serviceWalletType,
        float $amount,
        string $reason
    ): array {
        $span = $this->tracer->startSpan('myxn.service_wallet.transfer', [
            'wallet_type' => $serviceWalletType,
            'amount' => $amount,
        ]);

        try {
            $serviceWalletAddress = $this->walletManager->getWalletAddress($serviceWalletType);

            if (!$serviceWalletAddress) {
                throw new \Exception("Invalid service wallet type: {$serviceWalletType}");
            }

            $result = $this->transfer(
                $fromTokenAccount,
                $serviceWalletAddress,
                $amount,
                "service_wallet_{$serviceWalletType}",
                ['reason' => $reason]
            );

            if ($result['success']) {
                $this->walletManager->logFundTransfer(
                    $serviceWalletType,
                    $amount,
                    $reason,
                    $result['tx_signature']
                );
            }

            return $result;
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Burn MYXN tokens by transferring to burn wallet.
     *
     * @param string $fromTokenAccount
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function burn(string $fromTokenAccount, float $amount, string $reason = 'manual_burn'): array
    {
        $span = $this->tracer->startSpan('myxn.token.burn', [
            'amount' => $amount,
            'reason' => $reason,
        ]);

        try {
            $result = $this->transferToServiceWallet(
                $fromTokenAccount,
                'burn',
                $amount,
                $reason
            );

            if ($result['success']) {
                // Track burn statistics
                $this->trackBurnStats($amount);
            }

            return $result;
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Distribute platform fees to service wallets.
     *
     * @param string $fromTokenAccount
     * @param float $totalFee
     * @return array
     */
    public function distributePlatformFees(string $fromTokenAccount, float $totalFee): array
    {
        $span = $this->tracer->startSpan('myxn.fee.distribution', [
            'total_fee' => $totalFee,
        ]);

        $results = [];

        try {
            $distribution = $this->walletManager->calculateFeeDistribution($totalFee);

            foreach ($distribution as $type => $data) {
                if ($data['amount'] <= 0) {
                    continue;
                }

                $result = $this->transferToServiceWallet(
                    $fromTokenAccount,
                    $type,
                    $data['amount'],
                    'platform_fee_distribution'
                );

                $results[$type] = [
                    'amount' => $data['amount'],
                    'percentage' => $data['percentage'],
                    'success' => $result['success'],
                    'tx_signature' => $result['tx_signature'] ?? null,
                    'error' => $result['error'] ?? null,
                ];
            }

            $this->tracer->addEvent($span, 'distribution_completed', [
                'total_fee' => $totalFee,
                'distributions' => count($results),
            ]);

            return [
                'success' => true,
                'total_fee' => $totalFee,
                'distributions' => $results,
            ];
        } catch (\Exception $e) {
            $this->tracer->recordException($span, $e);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'distributions' => $results,
            ];
        } finally {
            $this->tracer->endSpan($span);
        }
    }

    /**
     * Generate a unique request ID for idempotency.
     *
     * @param string $prefix
     * @return string
     */
    protected function generateRequestId(string $prefix = 'myxn'): string
    {
        return sprintf(
            '%s_%s_%s',
            $prefix,
            now()->format('YmdHis'),
            bin2hex(random_bytes(8))
        );
    }

    /**
     * Track burn statistics in cache.
     *
     * @param float $amount
     * @return void
     */
    protected function trackBurnStats(float $amount): void
    {
        $key = 'myxn_burn_stats_' . now()->format('Y_m');
        $stats = Cache::get($key, ['total' => 0, 'count' => 0]);

        $stats['total'] += $amount;
        $stats['count'] += 1;
        $stats['last_burn'] = now()->toIso8601String();

        Cache::put($key, $stats, now()->addMonths(3));
    }

    /**
     * Get burn statistics.
     *
     * @param string|null $month Format: Y_m (e.g., 2025_12)
     * @return array
     */
    public function getBurnStats(?string $month = null): array
    {
        $key = 'myxn_burn_stats_' . ($month ?? now()->format('Y_m'));
        return Cache::get($key, ['total' => 0, 'count' => 0]);
    }

    /**
     * Get token info for API response.
     *
     * @return array
     */
    public function getTokenInfo(): array
    {
        return [
            'mint' => $this->getTokenMint(),
            'symbol' => $this->tokenConfig['symbol'],
            'name' => $this->tokenConfig['name'],
            'decimals' => $this->getDecimals(),
            'network' => config('myxn.network'),
        ];
    }
}
