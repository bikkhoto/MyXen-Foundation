<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin and authenticate
        $this->admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_ADMIN,
        ]);

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /**
     * Test admin can list all users.
     */
    public function test_admin_can_list_all_users(): void
    {
        // Create test users
        User::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'email', 'role', 'is_verified', 'kyc_status'],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertCount(5, $response->json('data.data'));
    }

    /**
     * Test admin can show specific user.
     */
    public function test_admin_can_show_specific_user(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@test.com',
        ]);

        Wallet::create([
            'user_id' => $user->id,
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => 'john@test.com',
                    ],
                ],
            ]);
    }

    /**
     * Test admin can list pending KYC users.
     */
    public function test_admin_can_list_pending_kyc(): void
    {
        // Create users with different KYC statuses
        User::factory()->create(['kyc_status' => 'pending']);
        User::factory()->create(['kyc_status' => 'pending']);
        User::factory()->create(['kyc_status' => 'approved']);
        User::factory()->create(['kyc_status' => 'rejected']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/kyc/pending');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data.data'));
    }

    /**
     * Test admin can show KYC document.
     */
    public function test_admin_can_show_kyc_document(): void
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/v1/admin/kyc/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'name',
                    'email',
                    'kyc_status',
                    'kyc_submitted_at',
                    'documents',
                ],
            ]);
    }

    /**
     * Test admin can approve KYC.
     */
    public function test_admin_can_approve_kyc(): void
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
            'is_verified' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/admin/kyc/{$user->id}/approve");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC approved successfully.',
                'data' => [
                    'kyc_status' => 'approved',
                    'is_verified' => true,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kyc_status' => 'approved',
            'is_verified' => true,
        ]);
    }

    /**
     * Test admin can reject KYC.
     */
    public function test_admin_can_reject_kyc(): void
    {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson("/api/v1/admin/kyc/{$user->id}/reject", [
                    'reason' => 'Invalid documents provided.',
                ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'KYC rejected successfully.',
                'data' => [
                    'kyc_status' => 'rejected',
                    'is_verified' => false,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'kyc_status' => 'rejected',
        ]);
    }

    /**
     * Test moderator cannot approve KYC.
     */
    public function test_moderator_cannot_approve_kyc(): void
    {
        $moderator = Admin::create([
            'name' => 'Moderator',
            'email' => 'moderator@test.com',
            'password' => 'password123',
            'role' => Admin::ROLE_MODERATOR,
        ]);

        $moderatorToken = $moderator->createToken('mod-token')->plainTextToken;

        $user = User::factory()->create(['kyc_status' => 'pending']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $moderatorToken,
        ])->postJson("/api/v1/admin/kyc/{$user->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test admin can list payment logs.
     */
    public function test_admin_can_list_payment_logs(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        // Create transactions
        Transaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 100.00,
            'type' => Transaction::TYPE_DEBIT,
            'status' => Transaction::STATUS_COMPLETED,
            'reference' => 'TEST123',
        ]);

        Transaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 50.00,
            'type' => Transaction::TYPE_CREDIT,
            'status' => Transaction::STATUS_COMPLETED,
            'reference' => 'TEST456',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/payments/logs');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertCount(2, $response->json('data.data'));
    }

    /**
     * Test admin can get dashboard statistics.
     */
    public function test_admin_can_get_dashboard_stats(): void
    {
        // Create test data
        User::factory()->count(10)->create(['is_verified' => true]);
        User::factory()->count(5)->create(['kyc_status' => 'pending']);

        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 1000.00,
        ]);

        Transaction::create([
            'wallet_id' => $wallet->id,
            'amount' => 100.00,
            'type' => Transaction::TYPE_DEBIT,
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        PaymentIntent::create([
            'sender_id' => $user->id,
            'receiver_id' => User::factory()->create()->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => PaymentIntent::STATUS_COMPLETED,
            'reference' => 'PI_TEST123',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/admin/stats/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_users',
                    'verified_users',
                    'pending_kyc',
                    'total_transactions',
                    'completed_transactions',
                    'pending_transactions',
                    'failed_transactions',
                    'total_payment_intents',
                    'completed_payments',
                    'pending_payments',
                    'failed_payments',
                    'total_transaction_volume',
                ],
            ])
            ->assertJson(['success' => true]);

        $this->assertGreaterThanOrEqual(10, $response->json('data.verified_users'));
        $this->assertGreaterThanOrEqual(5, $response->json('data.pending_kyc'));
    }

    /**
     * Test unauthenticated user cannot access admin dashboard.
     */
    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/stats/summary');
        $response->assertStatus(401);
    }
}
