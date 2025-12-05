<?php

namespace App\Services\MYXN;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ServiceWalletManager
 *
 * Manages official MYXN service wallets for different organizational purposes.
 * Handles wallet registry, validation, and fund distribution tracking.
 *
 * @package App\Services\MYXN
 */
class ServiceWalletManager
{
    /**
     * Official wallet configurations.
     *
     * @var array
     */
    protected array $wallets;

    /**
     * Fee distribution percentages.
     *
     * @var array
     */
    protected array $feeDistribution;

    /**
     * Create a new service wallet manager instance.
     */
    public function __construct()
    {
        $this->wallets = config('myxn.wallets', []);
        $this->feeDistribution = config('myxn.fee_distribution', []);
    }

    /**
     * Get all registered service wallets.
     *
     * @return Collection
     */
    public function getAllWallets(): Collection
    {
        return collect($this->wallets)->map(function ($wallet, $type) {
            return array_merge($wallet, ['type' => $type]);
        });
    }

    /**
     * Get a specific wallet by type.
     *
     * @param string $type treasury|mint|burn|charity|hr|marketing
     * @return array|null
     */
    public function getWallet(string $type): ?array
    {
        return $this->wallets[$type] ?? null;
    }

    /**
     * Get wallet address by type.
     *
     * @param string $type
     * @return string|null
     */
    public function getWalletAddress(string $type): ?string
    {
        return $this->wallets[$type]['address'] ?? null;
    }

    /**
     * Get treasury wallet address.
     *
     * @return string
     */
    public function getTreasuryAddress(): string
    {
        return $this->getWalletAddress('treasury');
    }

    /**
     * Get burn wallet address.
     *
     * @return string
     */
    public function getBurnWalletAddress(): string
    {
        return $this->getWalletAddress('burn');
    }

    /**
     * Get charity wallet address.
     *
     * @return string
     */
    public function getCharityWalletAddress(): string
    {
        return $this->getWalletAddress('charity');
    }

    /**
     * Get HR wallet address.
     *
     * @return string
     */
    public function getHRWalletAddress(): string
    {
        return $this->getWalletAddress('hr');
    }

    /**
     * Get marketing wallet address.
     *
     * @return string
     */
    public function getMarketingWalletAddress(): string
    {
        return $this->getWalletAddress('marketing');
    }

    /**
     * Validate if an address is an official service wallet.
     *
     * @param string $address
     * @return bool
     */
    public function isServiceWallet(string $address): bool
    {
        foreach ($this->wallets as $wallet) {
            if ($wallet['address'] === $address) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get wallet type by address.
     *
     * @param string $address
     * @return string|null
     */
    public function getWalletTypeByAddress(string $address): ?string
    {
        foreach ($this->wallets as $type => $wallet) {
            if ($wallet['address'] === $address) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Calculate fee distribution amounts from a total fee.
     *
     * @param float $totalFee
     * @return array
     */
    public function calculateFeeDistribution(float $totalFee): array
    {
        $distribution = [];

        foreach ($this->feeDistribution as $type => $percentage) {
            $amount = ($totalFee * $percentage) / 100;
            $distribution[$type] = [
                'amount' => $amount,
                'percentage' => $percentage,
                'wallet_address' => $this->getWalletAddress($type),
                'wallet_name' => $this->wallets[$type]['name'] ?? $type,
            ];
        }

        return $distribution;
    }

    /**
     * Get wallets eligible for auto-funding from platform fees.
     *
     * @return Collection
     */
    public function getAutoFundingWallets(): Collection
    {
        return collect($this->wallets)->filter(function ($wallet) {
            return $wallet['auto_funding'] ?? false;
        });
    }

    /**
     * Log a fund transfer to a service wallet.
     *
     * @param string $walletType
     * @param float $amount
     * @param string $reason
     * @param string|null $txSignature
     * @return void
     */
    public function logFundTransfer(
        string $walletType,
        float $amount,
        string $reason,
        ?string $txSignature = null
    ): void {
        $wallet = $this->getWallet($walletType);

        Log::channel('myxn')->info('Service wallet fund transfer', [
            'wallet_type' => $walletType,
            'wallet_name' => $wallet['name'] ?? $walletType,
            'wallet_address' => $wallet['address'] ?? 'unknown',
            'amount' => $amount,
            'reason' => $reason,
            'tx_signature' => $txSignature,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get wallet configuration for display/API response.
     *
     * @param string $type
     * @return array|null
     */
    public function getWalletInfo(string $type): ?array
    {
        $wallet = $this->getWallet($type);

        if (!$wallet) {
            return null;
        }

        return [
            'type' => $type,
            'name' => $wallet['name'],
            'description' => $wallet['description'],
            'address' => $wallet['address'],
            'auto_funding' => $wallet['auto_funding'] ?? false,
            'fee_percentage' => $this->feeDistribution[$type] ?? 0,
        ];
    }

    /**
     * Get all wallet info for display/API response.
     *
     * @return array
     */
    public function getAllWalletInfo(): array
    {
        return collect($this->wallets)->map(function ($wallet, $type) {
            return $this->getWalletInfo($type);
        })->filter()->values()->toArray();
    }
}
