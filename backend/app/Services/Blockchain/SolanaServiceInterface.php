<?php

declare(strict_types=1);

namespace App\Services\Blockchain;

/**
 * Solana Blockchain Service Interface
 *
 * @OA\Schema(
 *     schema="SolanaWallet",
 *     @OA\Property(property="address", type="string", description="Solana wallet address"),
 *     @OA\Property(property="publicKey", type="string", description="Base58 encoded public key")
 * )
 */
interface SolanaServiceInterface
{
    /**
     * Create a new Solana wallet
     *
     * @return array{address: string, publicKey: string}
     *
     * TODO: Implement secure key generation with proper entropy
     * TODO: Add secure key storage mechanism (HSM, KMS, or encrypted storage)
     */
    public function createWallet(): array;

    /**
     * Get balance for a given wallet address
     *
     * @param string $address Solana wallet address
     * @return float Balance in SOL
     *
     * TODO: Implement actual RPC call to Solana network
     */
    public function getBalance(string $address): float;

    /**
     * Send a transaction on the Solana network
     *
     * @param string $from Sender wallet address
     * @param string $to Recipient wallet address
     * @param float $amount Amount in SOL
     * @param string|null $memo Optional transaction memo
     * @return string Transaction signature
     *
     * TODO: Implement proper transaction signing
     * TODO: Add transaction confirmation waiting logic
     * TODO: Handle MYXN token transfers (SPL tokens)
     */
    public function sendTransaction(string $from, string $to, float $amount, ?string $memo = null): string;

    /**
     * Get transaction status
     *
     * @param string $signature Transaction signature
     * @return array{status: string, confirmations: int, error: string|null}
     *
     * TODO: Implement transaction status polling
     */
    public function getTransactionStatus(string $signature): array;
}
