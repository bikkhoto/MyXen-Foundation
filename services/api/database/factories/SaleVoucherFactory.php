<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Models\SaleVoucher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Models\SaleVoucher>
 */
class SaleVoucherFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SaleVoucher::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_pubkey' => $this->generateSolanaPubkey(),
            'buyer_pubkey' => $this->generateSolanaPubkey(),
            'max_allocation' => $this->faker->numberBetween(1000, 100000),
            'nonce' => (int) (microtime(true) * 1000000) + $this->faker->randomNumber(),
            'expiry_ts' => time() + $this->faker->numberBetween(3600, 86400), // 1 hour to 1 day
            'signature' => base64_encode(random_bytes(64)), // Fake 64-byte signature
            'issued_by' => null, // Can be overridden in tests
            'issued_at' => now(),
        ];
    }

    /**
     * Generate a fake Solana public key (Base58 encoded, 44 characters)
     *
     * @return string
     */
    private function generateSolanaPubkey(): string
    {
        // Generate a random 32-byte value and encode it as base58
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $pubkey = '';

        for ($i = 0; $i < 44; $i++) {
            $pubkey .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $pubkey;
    }

    /**
     * Indicate that the voucher is expired
     *
     * @return Factory
     */
    public function expired(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'expiry_ts' => time() - $this->faker->numberBetween(3600, 86400), // Expired
            ];
        });
    }

    /**
     * Indicate that the voucher is active (not expired)
     *
     * @return Factory
     */
    public function active(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'expiry_ts' => time() + $this->faker->numberBetween(3600, 86400), // Active
            ];
        });
    }

    /**
     * Set the admin who issued the voucher
     *
     * @param int $adminId
     * @return Factory
     */
    public function issuedBy(int $adminId): Factory
    {
        return $this->state(function (array $attributes) use ($adminId) {
            return [
                'issued_by' => $adminId,
            ];
        });
    }
}
