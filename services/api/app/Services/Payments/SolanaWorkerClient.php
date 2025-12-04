<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SolanaWorkerClient
 *
 * HTTP client wrapper for communicating with the Solana worker service.
 * Handles blockchain transfer requests and error handling.
 *
 * @package App\Services\Payments
 */
class SolanaWorkerClient
{
    /**
     * The base URL for the Solana worker service.
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * HTTP timeout in seconds.
     *
     * @var int
     */
    protected int $timeout;

    /**
     * Create a new Solana worker client instance.
     */
    public function __construct()
    {
        $this->baseUrl = rtrim(config('payments.solana_worker_url', 'http://localhost:8080'), '/');
        $this->timeout = config('payments.solana_worker_timeout', 30);
    }

    /**
     * Execute a token transfer on Solana blockchain.
     *
     * @param string $tokenMint The SPL token mint address
     * @param string|float $amount The amount to transfer
     * @param string $fromTokenAccount The sender's token account address
     * @param string $toTokenAccount The receiver's token account address
     * @param string $requestId Unique request identifier for idempotency
     * @return array{success: bool, txSignature?: string, error?: string}
     * @throws \Exception
     */
    public function transfer(
        string $tokenMint,
        $amount,
        string $fromTokenAccount,
        string $toTokenAccount,
        string $requestId
    ): array {
        $url = $this->baseUrl . '/transfer';

        $payload = [
            'tokenMint' => $tokenMint,
            'amount' => (string) $amount,
            'fromTokenAccount' => $fromTokenAccount,
            'toTokenAccount' => $toTokenAccount,
            'requestId' => $requestId,
        ];

        Log::info('Solana worker transfer request', [
            'url' => $url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Solana worker transfer successful', [
                    'requestId' => $requestId,
                    'txSignature' => $data['txSignature'] ?? null,
                ]);

                return [
                    'success' => true,
                    'txSignature' => $data['txSignature'] ?? '',
                ];
            }

            $errorMessage = $response->json('error') ?? $response->body();

            Log::error('Solana worker transfer failed', [
                'requestId' => $requestId,
                'status' => $response->status(),
                'error' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Solana worker connection failed', [
                'requestId' => $requestId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to connect to Solana worker: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Solana worker request exception', [
                'requestId' => $requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \Exception('Solana worker error: ' . $e->getMessage());
        }
    }

    /**
     * Get the current Solana worker health status.
     *
     * @return array{healthy: bool, error?: string}
     */
    public function health(): array
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl . '/health');

            return [
                'healthy' => $response->successful(),
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
