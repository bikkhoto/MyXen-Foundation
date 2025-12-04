<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        Role::create([
            'name' => 'user',
            'display_name' => 'User',
            'description' => 'Standard user',
        ]);

        $this->user = User::factory()->create();
        $this->user->assignRole('user');

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'address' => 'TEST_' . uniqid(),
            'public_key' => base64_encode(random_bytes(32)),
            'currency' => 'MYXN',
            'balance' => 1000,
            'is_primary' => true,
        ]);
    }

    public function test_user_can_get_wallet_list(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'wallets',
                'total_balance',
            ]);
    }

    public function test_user_can_get_wallet_details(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/wallet/{$this->wallet->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'wallet',
                'recent_transactions',
            ]);
    }

    public function test_user_cannot_access_other_users_wallet(): void
    {
        $otherUser = User::factory()->create();
        $otherWallet = Wallet::create([
            'user_id' => $otherUser->id,
            'address' => 'OTHER_' . uniqid(),
            'currency' => 'MYXN',
            'balance' => 500,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/wallet/{$otherWallet->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_transfer_to_another_wallet(): void
    {
        $recipientUser = User::factory()->create();
        $recipientWallet = Wallet::create([
            'user_id' => $recipientUser->id,
            'address' => 'RECIPIENT_' . uniqid(),
            'currency' => 'MYXN',
            'balance' => 0,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/transfer', [
                'to_address' => $recipientWallet->address,
                'amount' => 100,
                'description' => 'Test transfer',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Transfer completed successfully']);

        $this->assertEquals(900, $this->wallet->fresh()->balance);
        $this->assertEquals(100, $recipientWallet->fresh()->balance);
    }

    public function test_user_cannot_transfer_more_than_balance(): void
    {
        $recipientWallet = Wallet::create([
            'user_id' => User::factory()->create()->id,
            'address' => 'RECIPIENT_' . uniqid(),
            'currency' => 'MYXN',
            'balance' => 0,
            'is_primary' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/transfer', [
                'to_address' => $recipientWallet->address,
                'amount' => 5000, // More than balance
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Insufficient balance']);
    }

    public function test_user_can_request_withdrawal(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/wallet/withdraw', [
                'to_address' => 'EXTERNAL_SOLANA_ADDRESS',
                'amount' => 100,
            ]);

        $response->assertStatus(202)
            ->assertJson(['message' => 'Withdrawal queued for processing']);

        // In test environment, queue runs synchronously, so it may complete immediately
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->wallet->id,
            'type' => 'withdrawal',
        ]);
    }

    public function test_user_can_get_wallet_transactions(): void
    {
        Transaction::create([
            'wallet_id' => $this->wallet->id,
            'user_id' => $this->user->id,
            'type' => 'deposit',
            'direction' => 'in',
            'amount' => 100,
            'currency' => 'MYXN',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/wallet/{$this->wallet->id}/transactions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
    }
}
