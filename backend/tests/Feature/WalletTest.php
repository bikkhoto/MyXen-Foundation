<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'balance' => 100,
            'myxn_balance' => 1000,
        ]);
    }

    public function test_user_can_get_wallet(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/wallet');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'balance',
                    'myxn_balance',
                ],
            ]);
    }

    public function test_user_can_get_balance(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/wallet/balance');

        $response->assertStatus(200)
            ->assertJsonPath('data.sol_balance', '100.000000000')
            ->assertJsonPath('data.myxn_balance', '1000.000000000');
    }

    public function test_user_can_link_solana_address(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/wallet/link-solana', [
            'solana_address' => '5yKHV8H9n1XeyGPxJ9TF7HnNY6W5YvPBjcPvQrSxLjK1',
        ]);

        $response->assertStatus(200);

        $this->wallet->refresh();
        $this->assertEquals('5yKHV8H9n1XeyGPxJ9TF7HnNY6W5YvPBjcPvQrSxLjK1', $this->wallet->solana_address);
    }

    public function test_user_cannot_transfer_more_than_balance(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/wallet/transfer', [
            'to_address' => '3yKHV8H9n1XeyGPxJ9TF7HnNY6W5YvPBjcPvQrSxLjK2',
            'amount' => 1000, // More than available
            'currency' => 'SOL',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Insufficient balance');
    }

    public function test_user_can_get_transactions(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/wallet/transactions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
    }
}
