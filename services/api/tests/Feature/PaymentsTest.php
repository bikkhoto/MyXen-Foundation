<?php

namespace Tests\Feature;

use App\Jobs\ExecutePayment;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $sender;
    protected User $receiver;
    protected Wallet $senderWallet;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sender and receiver users
        $this->sender = User::factory()->create();
        $this->receiver = User::factory()->create();

        // Create sender wallet with balance
        $this->senderWallet = Wallet::create([
            'user_id' => $this->sender->id,
            'balance' => 1000.00,
            'currency' => 'USD',
        ]);

        // Generate auth token
        $this->token = $this->sender->createToken('test_token')->plainTextToken;
    }

    /**
     * Test creating a payment intent successfully.
     *
     * @return void
     */
    public function test_can_create_payment_intent(): void
    {
        $paymentData = [
            'amount' => 100.00,
            'currency' => 'USD',
            'receiver_id' => $this->receiver->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/payments/create-intent', $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'intent_id',
                    'reference',
                    'sender_id',
                    'receiver_id',
                    'amount',
                    'currency',
                    'status',
                    'created_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'sender_id' => $this->sender->id,
                    'receiver_id' => $this->receiver->id,
                    'amount' => '100.00',
                    'currency' => 'USD',
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('payment_intents', [
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 100.00,
            'status' => 'pending',
        ]);
    }

    /**
     * Test cannot create payment intent with insufficient balance.
     *
     * @return void
     */
    public function test_cannot_create_intent_with_insufficient_balance(): void
    {
        $paymentData = [
            'amount' => 2000.00, // More than wallet balance
            'currency' => 'USD',
            'receiver_id' => $this->receiver->id,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/payments/create-intent', $paymentData);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient balance.',
            ]);
    }

    /**
     * Test cannot create payment intent to self.
     *
     * @return void
     */
    public function test_cannot_create_intent_to_self(): void
    {
        $paymentData = [
            'amount' => 100.00,
            'currency' => 'USD',
            'receiver_id' => $this->sender->id, // Self payment
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/payments/create-intent', $paymentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['receiver_id']);
    }

    /**
     * Test executing a payment intent dispatches job.
     *
     * @return void
     */
    public function test_execute_payment_dispatches_job(): void
    {
        Queue::fake();

        // Create payment intent
        $intent = PaymentIntent::create([
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'reference' => 'PI_TEST123',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/payments/execute', [
                'intent_id' => $intent->id,
            ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'message' => 'Payment is being processed',
            ]);

        Queue::assertPushed(ExecutePayment::class, function ($job) use ($intent) {
            return $job->intentId === $intent->id;
        });

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => 'processing',
        ]);
    }

    /**
     * Test cannot execute payment intent not owned by user.
     *
     * @return void
     */
    public function test_cannot_execute_payment_intent_not_owned(): void
    {
        $otherUser = User::factory()->create();

        // Create payment intent for other user
        $intent = PaymentIntent::create([
            'sender_id' => $otherUser->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'reference' => 'PI_TEST456',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/payments/execute', [
                'intent_id' => $intent->id,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized. You are not the sender of this payment.',
            ]);
    }

    /**
     * Test payment execution job completes successfully.
     *
     * @return void
     */
    public function test_payment_execution_job_completes_successfully(): void
    {
        // Create receiver wallet
        $receiverWallet = Wallet::create([
            'user_id' => $this->receiver->id,
            'balance' => 0.00,
            'currency' => 'USD',
        ]);

        // Create payment intent
        $intent = PaymentIntent::create([
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'reference' => 'PI_EXEC123',
            'status' => 'processing',
        ]);

        $initialSenderBalance = $this->senderWallet->balance;

        // Execute the job
        $job = new ExecutePayment($intent->id);
        $job->handle();

        // Refresh models
        $this->senderWallet->refresh();
        $receiverWallet->refresh();
        $intent->refresh();

        // Assert balances updated
        $this->assertEquals($initialSenderBalance - 100.00, $this->senderWallet->balance);
        $this->assertEquals(100.00, $receiverWallet->balance);

        // Assert intent completed
        $this->assertEquals('completed', $intent->status);

        // Assert transactions created
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $this->senderWallet->id,
            'amount' => 100.00,
            'type' => 'debit',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $receiverWallet->id,
            'amount' => 100.00,
            'type' => 'credit',
            'status' => 'completed',
        ]);
    }

    /**
     * Test payment execution fails with insufficient balance.
     *
     * @return void
     */
    public function test_payment_execution_fails_with_insufficient_balance(): void
    {
        // Create payment intent with amount greater than balance
        $intent = PaymentIntent::create([
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 2000.00, // More than wallet balance
            'currency' => 'USD',
            'reference' => 'PI_FAIL123',
            'status' => 'processing',
        ]);

        $initialSenderBalance = $this->senderWallet->balance;

        // Execute the job - expect exception
        $job = new ExecutePayment($intent->id);

        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        }

        // Refresh models
        $this->senderWallet->refresh();
        $intent->refresh();

        // Assert balance unchanged
        $this->assertEquals($initialSenderBalance, $this->senderWallet->balance);

        // Assert intent marked as failed
        $this->assertEquals('failed', $intent->status);
    }

    /**
     * Test can get payment intent status.
     *
     * @return void
     */
    public function test_can_get_payment_intent_status(): void
    {
        $intent = PaymentIntent::create([
            'sender_id' => $this->sender->id,
            'receiver_id' => $this->receiver->id,
            'amount' => 100.00,
            'currency' => 'USD',
            'reference' => 'PI_STATUS123',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/payments/intent/{$intent->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'intent_id' => $intent->id,
                    'reference' => 'PI_STATUS123',
                    'status' => 'pending',
                ],
            ]);
    }

    /**
     * Test unauthenticated user cannot access payment endpoints.
     *
     * @return void
     */
    public function test_unauthenticated_user_cannot_access_payment_endpoints(): void
    {
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100.00,
            'receiver_id' => $this->receiver->id,
        ]);

        $response->assertStatus(401);
    }
}
