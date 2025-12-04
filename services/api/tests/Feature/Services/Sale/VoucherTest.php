<?php

namespace Tests\Feature\Services\Sale;

use App\Models\Admin;
use App\Models\Models\SaleVoucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * VoucherTest
 *
 * Tests for Solana presale voucher issuance and management.
 * These tests verify voucher signing, storage, and retrieval.
 *
 * NOTE: These tests require the voucher signer keypair to be present.
 * Set VOUCHER_SIGNER_KEYPATH in .env.testing or ensure keypair exists at
 * ~/.myxen/keys/voucher-signer.json
 */
class VoucherTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Sample Solana public keys for testing (Base58 format)
     */
    private const SAMPLE_BUYER_PUBKEY = '9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM';
    private const SAMPLE_SALE_PUBKEY = 'FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH';

    /**
     * Admin user for authenticated requests
     *
     * @var Admin
     */
    private Admin $admin;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate:fresh');

        // Create admin user
        $this->admin = Admin::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test that admin can issue a voucher with valid signature
     */
    public function test_admin_can_issue_voucher(): void
    {
        // Skip test if keypair is not configured
        $keypairPath = env('VOUCHER_SIGNER_KEYPATH', $_SERVER['HOME'] . '/.myxen/keys/voucher-signer.json');
        if (!file_exists($keypairPath)) {
            $this->markTestSkipped('Voucher signer keypair not found. Generate it with: solana-keygen new --outfile ' . $keypairPath);
        }

        $expiryTs = time() + 3600; // 1 hour from now

        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 10000,
                'expiry_ts' => $expiryTs,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'voucher' => [
                    'buyer',
                    'sale',
                    'max_allocation',
                    'nonce',
                    'expiry_ts',
                ],
                'signature',
                'signer_pubkey',
            ])
            ->assertJson([
                'success' => true,
                'voucher' => [
                    'buyer' => self::SAMPLE_BUYER_PUBKEY,
                    'sale' => self::SAMPLE_SALE_PUBKEY,
                    'max_allocation' => 10000,
                    'expiry_ts' => $expiryTs,
                ],
            ]);

        // Verify signature is base64 encoded and 64 bytes when decoded
        $signature = $response->json('signature');
        $this->assertNotEmpty($signature);

        $signatureBytes = base64_decode($signature, true);
        $this->assertNotFalse($signatureBytes, 'Signature should be valid base64');
        $this->assertEquals(64, strlen($signatureBytes), 'Ed25519 signature should be 64 bytes');

        // Verify signer pubkey is base58 encoded and 44 characters
        $signerPubkey = $response->json('signer_pubkey');
        $this->assertEquals(44, strlen($signerPubkey), 'Base58 public key should be 44 characters');

        // Verify voucher was stored in database
        $this->assertDatabaseHas('sale_vouchers', [
            'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
            'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
            'max_allocation' => 10000,
            'expiry_ts' => $expiryTs,
            'issued_by' => $this->admin->id,
        ]);
    }

    /**
     * Test that unauthorized users cannot issue vouchers
     */
    public function test_unauthorized_user_cannot_issue_voucher(): void
    {
        $response = $this->postJson('/api/v1/sale/whitelist', [
            'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
            'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
            'max_allocation' => 10000,
            'expiry_ts' => time() + 3600,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test that vouchers have unique nonces
     */
    public function test_voucher_has_unique_nonce(): void
    {
        // Skip test if keypair is not configured
        $keypairPath = env('VOUCHER_SIGNER_KEYPATH', $_SERVER['HOME'] . '/.myxen/keys/voucher-signer.json');
        if (!file_exists($keypairPath)) {
            $this->markTestSkipped('Voucher signer keypair not found.');
        }

        $expiryTs = time() + 3600;

        // Issue first voucher
        $response1 = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 10000,
                'expiry_ts' => $expiryTs,
            ]);

        $nonce1 = $response1->json('voucher.nonce');

        // Issue second voucher
        $response2 = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 5000,
                'expiry_ts' => $expiryTs,
            ]);

        $nonce2 = $response2->json('voucher.nonce');

        // Verify nonces are different
        $this->assertNotEquals($nonce1, $nonce2, 'Voucher nonces must be unique');

        // Verify both vouchers exist in database with different nonces
        $this->assertEquals(2, SaleVoucher::count());
        $this->assertDatabaseHas('sale_vouchers', ['nonce' => $nonce1]);
        $this->assertDatabaseHas('sale_vouchers', ['nonce' => $nonce2]);
    }

    /**
     * Test validation errors for invalid input
     */
    public function test_validation_errors_for_invalid_input(): void
    {
        // Test missing buyer_pubkey
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 10000,
                'expiry_ts' => time() + 3600,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['buyer_pubkey']);

        // Test invalid buyer_pubkey length (not 44 chars)
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => 'invalid',
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 10000,
                'expiry_ts' => time() + 3600,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['buyer_pubkey']);

        // Test invalid max_allocation (must be positive)
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => -1000,
                'expiry_ts' => time() + 3600,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_allocation']);

        // Test expiry_ts in the past
        $response = $this->actingAs($this->admin, 'admin')
            ->postJson('/api/v1/sale/whitelist', [
                'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
                'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
                'max_allocation' => 10000,
                'expiry_ts' => time() - 3600, // 1 hour ago
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expiry_ts']);
    }

    /**
     * Test retrieving vouchers for a specific buyer
     */
    public function test_can_retrieve_buyer_vouchers(): void
    {
        // Create some vouchers in database
        SaleVoucher::factory()->count(3)->create([
            'buyer_pubkey' => self::SAMPLE_BUYER_PUBKEY,
            'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
        ]);

        // Create vouchers for different buyer
        SaleVoucher::factory()->count(2)->create([
            'buyer_pubkey' => 'DifferentBuyerPublicKey1234567890ABCDEF',
            'sale_pubkey' => self::SAMPLE_SALE_PUBKEY,
        ]);

        $response = $this->getJson('/api/v1/sale/vouchers/' . self::SAMPLE_BUYER_PUBKEY);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'vouchers' => [
                    '*' => [
                        'id',
                        'buyer_pubkey',
                        'sale_pubkey',
                        'max_allocation',
                        'nonce',
                        'expiry_ts',
                        'signature',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify only vouchers for the specified buyer are returned
        $vouchers = $response->json('vouchers');
        $this->assertCount(3, $vouchers);

        foreach ($vouchers as $voucher) {
            $this->assertEquals(self::SAMPLE_BUYER_PUBKEY, $voucher['buyer_pubkey']);
        }
    }

    /**
     * Test SaleVoucher model helper methods
     */
    public function test_sale_voucher_model_helpers(): void
    {
        // Test isExpired() method
        $expiredVoucher = SaleVoucher::factory()->create([
            'expiry_ts' => time() - 3600, // 1 hour ago
        ]);

        $activeVoucher = SaleVoucher::factory()->create([
            'expiry_ts' => time() + 3600, // 1 hour from now
        ]);

        $this->assertTrue($expiredVoucher->isExpired());
        $this->assertFalse($activeVoucher->isExpired());

        // Test toVoucherData() method
        $voucherData = $activeVoucher->toVoucherData();
        $this->assertIsArray($voucherData);
        $this->assertArrayHasKey('buyer', $voucherData);
        $this->assertArrayHasKey('sale', $voucherData);
        $this->assertArrayHasKey('max_allocation', $voucherData);
        $this->assertArrayHasKey('nonce', $voucherData);
        $this->assertArrayHasKey('expiry_ts', $voucherData);

        // Test generateNonce() method
        $nonce1 = SaleVoucher::generateNonce();
        usleep(100); // Small delay
        $nonce2 = SaleVoucher::generateNonce();
        $this->assertNotEquals($nonce1, $nonce2);
    }

    /**
     * Test SaleVoucher model scopes
     */
    public function test_sale_voucher_scopes(): void
    {
        $buyer1 = self::SAMPLE_BUYER_PUBKEY;
        $buyer2 = 'AnotherBuyerPublicKey567890ABCDEFGHIJKLM';
        $sale1 = self::SAMPLE_SALE_PUBKEY;
        $sale2 = 'AnotherSalePublicKey567890ABCDEFGHIJKLMN';

        // Create vouchers with different combinations
        SaleVoucher::factory()->count(2)->create([
            'buyer_pubkey' => $buyer1,
            'sale_pubkey' => $sale1,
            'expiry_ts' => time() + 3600, // Active
        ]);

        SaleVoucher::factory()->create([
            'buyer_pubkey' => $buyer2,
            'sale_pubkey' => $sale1,
            'expiry_ts' => time() - 3600, // Expired
        ]);

        SaleVoucher::factory()->create([
            'buyer_pubkey' => $buyer1,
            'sale_pubkey' => $sale2,
            'expiry_ts' => time() + 3600, // Active
        ]);

        // Test byBuyer scope
        $buyer1Vouchers = SaleVoucher::byBuyer($buyer1)->get();
        $this->assertCount(3, $buyer1Vouchers);

        // Test bySale scope
        $sale1Vouchers = SaleVoucher::bySale($sale1)->get();
        $this->assertCount(3, $sale1Vouchers);

        // Test active scope
        $activeVouchers = SaleVoucher::active()->get();
        $this->assertCount(3, $activeVouchers);

        // Test expired scope
        $expiredVouchers = SaleVoucher::expired()->get();
        $this->assertCount(1, $expiredVouchers);

        // Test combining scopes
        $buyer1ActiveVouchers = SaleVoucher::byBuyer($buyer1)->active()->get();
        $this->assertCount(3, $buyer1ActiveVouchers);
    }
}
