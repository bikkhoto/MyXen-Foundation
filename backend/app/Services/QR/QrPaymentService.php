<?php

namespace App\Services\QR;

use App\Models\Merchant;
use Illuminate\Support\Str;

class QrPaymentService
{
    /**
     * Generate QR code for merchant.
     */
    public function generateQrCode(Merchant $merchant): string
    {
        $qrCode = 'MYXEN-' . strtoupper(Str::random(16));
        $merchant->qr_code = $qrCode;
        $merchant->save();

        return $qrCode;
    }

    /**
     * Parse QR code data.
     */
    public function parseQrCode(string $qrCode): ?array
    {
        $merchant = Merchant::where('qr_code', $qrCode)
            ->where('status', 'active')
            ->first();

        if (!$merchant) {
            return null;
        }

        return [
            'merchant_id' => $merchant->id,
            'business_name' => $merchant->business_name,
            'wallet_address' => $merchant->wallet_address,
            'commission_rate' => $merchant->commission_rate,
        ];
    }

    /**
     * Validate QR code format.
     */
    public function isValidQrFormat(string $qrCode): bool
    {
        return preg_match('/^MYXEN-[A-Z0-9]{16}$/', $qrCode) === 1;
    }

    /**
     * Generate payment URL.
     */
    public function generatePaymentUrl(string $qrCode, float $amount, string $currency = 'SOL'): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/pay/{$qrCode}?amount={$amount}&currency={$currency}";
    }

    /**
     * Generate QR code image data (base64).
     */
    public function generateQrImage(string $data): string
    {
        // This would integrate with a QR code library
        // For now, return a placeholder
        return 'data:image/png;base64,' . base64_encode($data);
    }
}
