<?php

namespace Tests\Feature\Payments;

use App\Jobs\ExecutePaymentJob;
use App\Models\Admin;
use App\Models\Models\PaymentIntent;
use App\Models\Models\Transaction;
use App\Models\Models\Wallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PaymentsTest
 *
 * Comprehensive test suite for the Payments Engine module.
 * Tests user payment flows, admin operations, Solana worker integration,
 * and error handling with rollback mechanisms.
 *
 * @package Tests\Feature\Payments
 */
class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Wallet $wallet;
    protected Admin $admin;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create wallet for user
        $this->wallet = Wallet::create([
            'user_id' => $this->user->id,
            'address' => 'sender-token-account-123',
            'currency' => 'MYXN',
            'balance' => '1000.000000000',
            'status' => 'active',
        ]);

        // Create admin user
        $this->admin = Admin::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);
    }

    /**
     * Test that create-intent requires authentication.
     */
    public function test_create_intent_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100,
            'currency' => 'MYXN',
            'receiver_address' => 'receiver-token-account-456',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test create-intent reserves funds and creates payment intent.
     */
    public function test_create_intent_reserves_funds(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/create-intent', [
                'amount' => 100.5,
                'currency' => 'MYXN',
                'receiver_address' => 'receiver-token-account-456',
                'memo' => 'Test payment',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'intent_id',
                'reference',
                'status',
                'amount',
                'currency',
            ]);

        // Verify payment intent was created
        $this->assertDatabaseHas('payment_intents', [
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'amount' => '100.500000000',
            'currency' => 'MYXN',
            'receiver_wallet_address' => 'receiver-token-account-456',
            'status' => 'created',
        ]);

        // Verify pending debit transaction was created
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->wallet->id,
            'amount' => '100.500000000',
            'type' => 'debit',
            'status' => 'pending',
        ]);

        // Verify funds were debited from wallet
        $this->wallet->refresh();
        $this->assertEquals('899.500000000', $this->wallet->balance);
    }

    /**
     * Test execute dispatches job and completes on worker success.
     */
    public function test_execute_dispatches_job_and_completes_on_worker_success(): void
    {
        // Create payment intent
        $intent = PaymentIntent::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'amount' => '50.000000000',
            'currency' => 'MYXN',
            'receiver_wallet_address' => 'receiver-token-account-789',
            'status' => 'created',
            'meta' => [
                'reference' => 'test-uuid-12345',
            ],
        ]);

        // Debit wallet to simulate fund reservation
        $this->wallet->debit(50);

        // Fake the queue
        Bus::fake();

        // Execute payment
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/execute', [
                'intent_id' => $intent->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment execution started.',
                'intent_id' => $intent->id,
            ]);

        // Verify job was dispatched
        Bus::assertDispatched(ExecutePaymentJob::class, function ($job) use ($intent) {
            return $job->paymentIntentId === $intent->id;
        });

        // Verify intent status updated to ready
        $intent->refresh();
        $this->assertEquals('ready', $intent->status);

        // Now test the actual job execution with HTTP fake
        Bus::fake(false); // Disable fake to actually run the job
        Http::fake([
            '*/transfer' => Http::response([
                'success' => true,
                'txSignature' => 'solana-tx-signature-abc123',
            ], 200),
        ]);

        // Create receiver wallet
        $receiverWallet = Wallet::create([
            'address' => 'receiver-token-account-789',
            'currency' => 'MYXN',
            'balance' => '0.000000000',
            'status' => 'active',
        ]);

        // Manually run the job
        $job = new ExecutePaymentJob($intent->id);
        $paymentService = app(\App\Services\Payments\PaymentService::class);
        $solanaClient = app(\App\Services\Payments\SolanaWorkerClient::class);
        $job->handle($paymentService, $solanaClient);

        // Verify debit transaction marked completed
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->wallet->id,
            'type' => 'debit',
            'status' => 'completed',
            'external_tx' => 'solana-tx-signature-abc123',
        ]);

        // Verify credit transaction created for receiver
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $receiverWallet->id,
            'type' => 'credit',
            'status' => 'completed',
            'external_tx' => 'solana-tx-signature-abc123',
        ]);

        // Verify receiver wallet credited
        $receiverWallet->refresh();
        $this->assertEquals('50.000000000', $receiverWallet->balance);

        // Verify intent marked completed
        $intent->refresh();
        $this->assertEquals('completed', $intent->status);
    }

    /**
     * Test execute rolls back on worker failure.
     */
    public function test_execute_rolls_back_on_worker_failure(): void
    {
        // Create payment intent
        $intent = PaymentIntent::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'amount' => '75.000000000',
            'currency' => 'MYXN',
            'receiver_wallet_address' => 'receiver-token-account-fail',
            'status' => 'created',
            'meta' => [
                'reference' => 'test-uuid-fail-67890',
            ],
        ]);

        // Get initial balance
        $initialBalance = $this->wallet->balance;

        // Fake HTTP to return failure
        Http::fake([
            '*/transfer' => Http::response([
                'success' => false,
                'error' => 'Insufficient balance on blockchain',
            ], 400),
        ]);

        // Manually run the job
        $job = new ExecutePaymentJob($intent->id);
        $paymentService = app(\App\Services\Payments\PaymentService::class);
        $solanaClient = app(\App\Services\Payments\SolanaWorkerClient::class);

        try {
            $job->handle($paymentService, $solanaClient);
        } catch (\Exception $e) {
            // Expected to throw
        }

        // Verify wallet balance was credited back (rollback)
        $this->wallet->refresh();
        $this->assertEquals($initialBalance, $this->wallet->balance);

        // Verify pending transaction marked as failed
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->wallet->id,
            'type' => 'debit',
            'status' => 'failed',
        ]);

        // Verify intent marked as failed
        $intent->refresh();
        $this->assertEquals('failed', $intent->status);
    }

    /**
     * Test admin can view payment logs.
     */
    public function test_admin_can_view_payment_logs(): void
    {
        // Create some transactions
        Transaction::create([
            'wallet_id' => $this->wallet->id,
            'amount' => '10.000000000',
            'type' => 'debit',
            'status' => 'completed',
            'reference' => 'ref-001',
        ]);

        Transaction::create([
            'wallet_id' => $this->wallet->id,
            'amount' => '5.000000000',
            'type' => 'credit',
            'status' => 'completed',
            'reference' => 'ref-002',
        ]);

        $token = $this->admin->createToken('admin-token', ['*'], 'admin')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/payments/logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'wallet_id',
                        'amount',
                        'type',
                        'status',
                        'reference',
                        'created_at',
                    ],
                ],
                'pagination',
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    /**
     * Test admin reconcile works.
     */
    public function test_admin_reconcile_works(): void
    {
        // Create payment intent
        $intent = PaymentIntent::create([
            'user_id' => $this->user->id,
            'wallet_id' => $this->wallet->id,
            'amount' => '25.000000000',
            'currency' => 'MYXN',
            'receiver_wallet_address' => 'receiver-reconcile',
            'status' => 'executing',
            'meta' => [
                'reference' => 'reconcile-uuid-111',
            ],
        ]);

        // Create pending transaction
        Transaction::create([
            'wallet_id' => $this->wallet->id,
            'amount' => '25.000000000',
            'type' => 'debit',
            'status' => 'pending',
            'reference' => 'reconcile-uuid-111',
        ]);

        $token = $this->admin->createToken('admin-token', ['*'], 'admin')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/admin/payments/{$intent->id}/reconcile", [
                'external_tx' => 'manual-tx-signature-xyz',
                'notes' => 'Manually reconciled by admin',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment intent reconciled successfully.',
            ]);

        // Verify transaction marked completed
        $this->assertDatabaseHas('transactions', [
            'reference' => 'reconcile-uuid-111',
            'status' => 'completed',
            'external_tx' => 'manual-tx-signature-xyz',
        ]);

        // Verify intent marked completed with admin notes
        $intent->refresh();
        $this->assertEquals('completed', $intent->status);
        $this->assertArrayHasKey('admin_notes', $intent->meta);
        $this->assertEquals('Manually reconciled by admin', $intent->meta['admin_notes']);
    }
}
