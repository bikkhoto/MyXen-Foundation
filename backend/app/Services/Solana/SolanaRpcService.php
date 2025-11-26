<?php

namespace App\Services\Solana;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SolanaRpcService
{
    /**
     * Solana address validation constants
     */
    private const ADDRESS_MIN_LENGTH = 32;
    private const ADDRESS_MAX_LENGTH = 44;
    private const LAMPORTS_PER_SOL = 1_000_000_000;

    protected string $rpcUrl;
    protected string $network;

    public function __construct()
    {
        $this->rpcUrl = config('solana.rpc_url');
        $this->network = config('solana.network');
    }

    /**
     * Get SOL balance for an address.
     */
    public function getBalance(string $address): ?float
    {
        $response = $this->rpcCall('getBalance', [$address]);

        if ($response && isset($response['result']['value'])) {
            return $response['result']['value'] / self::LAMPORTS_PER_SOL;
        }

        return null;
    }

    /**
     * Get token balance (MYXN).
     */
    public function getTokenBalance(string $address, string $tokenMint): ?float
    {
        $response = $this->rpcCall('getTokenAccountsByOwner', [
            $address,
            ['mint' => $tokenMint],
            ['encoding' => 'jsonParsed'],
        ]);

        if ($response && isset($response['result']['value'][0])) {
            $tokenAmount = $response['result']['value'][0]['account']['data']['parsed']['info']['tokenAmount'];
            return (float) $tokenAmount['uiAmount'];
        }

        return 0;
    }

    /**
     * Get transaction details.
     */
    public function getTransaction(string $signature): ?array
    {
        $response = $this->rpcCall('getTransaction', [
            $signature,
            ['encoding' => 'jsonParsed', 'maxSupportedTransactionVersion' => 0],
        ]);

        return $response['result'] ?? null;
    }

    /**
     * Confirm transaction.
     */
    public function confirmTransaction(string $signature, int $timeout = 30): bool
    {
        $startTime = time();

        while ((time() - $startTime) < $timeout) {
            $response = $this->rpcCall('getSignatureStatuses', [[$signature]]);

            if ($response && isset($response['result']['value'][0])) {
                $status = $response['result']['value'][0];
                if ($status && $status['confirmationStatus'] === 'finalized') {
                    return true;
                }
            }

            sleep(1);
        }

        return false;
    }

    /**
     * Get recent blockhash.
     */
    public function getRecentBlockhash(): ?string
    {
        $response = $this->rpcCall('getLatestBlockhash');

        return $response['result']['value']['blockhash'] ?? null;
    }

    /**
     * Get minimum balance for rent exemption.
     */
    public function getMinimumBalanceForRentExemption(int $dataSize): ?int
    {
        $response = $this->rpcCall('getMinimumBalanceForRentExemption', [$dataSize]);

        return $response['result'] ?? null;
    }

    /**
     * Check if address is valid.
     */
    public function isValidAddress(string $address): bool
    {
        // Basic validation - Solana addresses are base58 encoded
        if (strlen($address) < self::ADDRESS_MIN_LENGTH || strlen($address) > self::ADDRESS_MAX_LENGTH) {
            return false;
        }

        // Check for valid base58 characters
        return preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $address) === 1;
    }

    /**
     * Make RPC call.
     */
    protected function rpcCall(string $method, array $params = []): ?array
    {
        try {
            $response = Http::timeout(10)->post($this->rpcUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => $method,
                'params' => $params,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Solana RPC error', [
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Solana RPC exception', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
