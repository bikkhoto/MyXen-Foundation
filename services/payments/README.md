# Payments Service

Comprehensive payment processing service for MyXen platform with wallet management, transaction tracking, and queue-based payment execution.

## Overview

The Payments Service handles peer-to-peer payments between users with a two-phase approach:
1. **Payment Intent Creation** - Validates sender balance and creates intent
2. **Payment Execution** - Asynchronously processes the payment via queue job

This architecture supports future integration with blockchain payment processors (e.g., Solana) and provides rollback capabilities for failed transactions.

## Architecture

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Client    │────────>│  Controller  │────────>│    Queue    │
│   (API)     │         │  (Laravel)   │         │    (Job)    │
└─────────────┘         └──────────────┘         └─────────────┘
                               │                         │
                               v                         v
                        ┌──────────────┐         ┌─────────────┐
                        │   Payment    │         │   Wallet    │
                        │   Intent     │         │  Operations │
                        └──────────────┘         └─────────────┘
                                                         │
                                                         v
                                                  ┌─────────────┐
                                                  │ Transaction │
                                                  │   Records   │
                                                  └─────────────┘
```


## Database Schema

### Wallets Table
```sql
- id (bigint, primary key)
- user_id (bigint, foreign key -> users.id)
- balance (decimal 20,2, default: 0.00)
- currency (varchar 10, default: 'USD')
- created_at, updated_at
- Indexes: user_id, (user_id, currency)
```

### Transactions Table
```sql
- id (bigint, primary key)
- wallet_id (bigint, foreign key -> wallets.id)
- amount (decimal 20,2)
- type (enum: 'debit', 'credit')
- status (enum: 'pending', 'completed', 'failed')
- external_tx (varchar, nullable) - For blockchain tx hashes
- reference (varchar, nullable) - Payment intent reference
- description (text, nullable)
- created_at, updated_at
- Indexes: wallet_id, status, (wallet_id, status), external_tx
```

### Payment Intents Table
```sql
- id (bigint, primary key)
- sender_id (bigint, foreign key -> users.id)
- receiver_id (bigint, foreign key -> users.id)
- amount (decimal 20,2)
- currency (varchar 10, default: 'USD')
- status (enum: 'pending', 'processing', 'completed', 'failed', 'cancelled')
- reference (varchar, unique)
- metadata (json, nullable)
- created_at, updated_at
- Indexes: sender_id, receiver_id, status, reference
```

## API Endpoints

### 1. Create Payment Intent
**POST** `/api/v1/payments/create-intent`

Creates a payment intent after validating sender's balance.

**Headers:**
```
Authorization: Bearer {token}
```

**Request:**
```json
{
  "amount": 100.00,
  "currency": "USD",
  "receiver_id": 5
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Payment intent created successfully",
  "data": {
    "intent_id": 1,
    "reference": "PI_ABC123XYZ456",
    "sender_id": 2,
    "receiver_id": 5,
    "amount": "100.00",
    "currency": "USD",
    "status": "pending",
    "created_at": "2025-12-03T14:20:00.000000Z"
  }
}
```

**Error (400) - Insufficient Balance:**
```json
{
  "success": false,
  "message": "Insufficient balance.",
  "data": {
    "required": 100.00,
    "available": 50.00
  }
}
```

### 2. Execute Payment
**POST** `/api/v1/payments/execute`

Dispatches payment execution job to process the transaction.

**Headers:**
```
Authorization: Bearer {token}
```

**Request:**
```json
{
  "intent_id": 1
}
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "message": "Payment is being processed",
  "data": {
    "intent_id": 1,
    "reference": "PI_ABC123XYZ456",
    "status": "processing"
  }
}
```

### 3. Get Payment Intent Status
**GET** `/api/v1/payments/intent/{intentId}`

Retrieves the current status of a payment intent.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "intent_id": 1,
    "reference": "PI_ABC123XYZ456",
    "sender_id": 2,
    "receiver_id": 5,
    "amount": "100.00",
    "currency": "USD",
    "status": "completed",
    "created_at": "2025-12-03T14:20:00.000000Z",
    "updated_at": "2025-12-03T14:20:15.000000Z"
  }
}
```

## Queue Job: ExecutePayment

The `ExecutePayment` job handles the actual payment processing with full transaction rollback support.

### Features:
- ✅ Database transaction with row locking
- ✅ Automatic rollback on failure
- ✅ Retry mechanism (3 attempts)
- ✅ Comprehensive logging
- ✅ Dual transaction records (debit + credit)
- ✅ Balance validation before execution
- ✅ Auto-creates receiver wallet if missing

### Workflow:
1. Lock sender and receiver wallets
2. Validate sender balance
3. Create debit transaction (pending)
4. Debit sender wallet
5. Mark debit transaction as completed
6. Create credit transaction (pending)
7. Credit receiver wallet
8. Mark credit transaction as completed
9. Mark payment intent as completed
10. Commit database transaction

**On Failure:**
- Rollback all database changes
- Mark intent as failed
- Mark pending transactions as failed
- Log error details
- Retry up to 3 times

## Models

### Wallet Model
```php
// Helper methods
$wallet->hasSufficientBalance(100.00);  // Check balance
$wallet->debit(100.00);                  // Debit amount
$wallet->credit(100.00);                 // Credit amount

// Relationships
$wallet->user();                         // BelongsTo User
$wallet->transactions();                 // HasMany Transaction
```

### Transaction Model
```php
// Constants
Transaction::TYPE_DEBIT
Transaction::TYPE_CREDIT
Transaction::STATUS_PENDING
Transaction::STATUS_COMPLETED
Transaction::STATUS_FAILED

// Helper methods
$transaction->markAsCompleted();
$transaction->markAsFailed();
$transaction->isPending();
$transaction->isCompleted();
$transaction->isFailed();
```

### PaymentIntent Model
```php
// Constants
PaymentIntent::STATUS_PENDING
PaymentIntent::STATUS_PROCESSING
PaymentIntent::STATUS_COMPLETED
PaymentIntent::STATUS_FAILED
PaymentIntent::STATUS_CANCELLED

// Helper methods
$intent->markAsProcessing();
$intent->markAsCompleted();
$intent->markAsFailed();
```

## Testing

Run payment tests:
```bash
php artisan test --filter PaymentsTest
```

**Test Coverage:**
- ✅ Create payment intent
- ✅ Insufficient balance validation
- ✅ Self-payment prevention
- ✅ Payment execution job dispatch
- ✅ Authorization checks
- ✅ Successful payment execution
- ✅ Failed payment rollback
- ✅ Intent status retrieval
- ✅ Unauthenticated access prevention

## Setup & Migration

1. Run migrations:
```bash
php artisan migrate
```

2. Create user wallets:
```bash
# Via Tinker
php artisan tinker
> $user = User::find(1);
> Wallet::create(['user_id' => $user->id, 'balance' => 1000.00, 'currency' => 'USD']);
```

3. Configure queue worker:
```bash
# Development
php artisan queue:work

# Production (with supervisor)
php artisan queue:work --tries=3 --timeout=120
```

## Extending to On-Chain (Solana) Payments

The current implementation is designed to be extended for blockchain integration. Here's how to integrate Solana on-chain payments:

### Architecture for Blockchain Integration

```
┌──────────────┐         ┌──────────────┐         ┌─────────────────┐
│  Laravel API │────────>│  Queue Job   │────────>│ Solana Service  │
│  (Intent)    │         │ (Execute)    │         │ (Microservice)  │
└──────────────┘         └──────────────┘         └─────────────────┘
                                │                          │
                                v                          v
                         ┌──────────────┐         ┌─────────────────┐
                         │  Off-Chain   │         │   Solana RPC    │
                         │  DB Record   │         │   (On-Chain)    │
                         └──────────────┘         └─────────────────┘
```

### Step 1: Create Solana Payment Microservice

Create a separate Node.js/Rust microservice in `services/solana-processor/`:

**Responsibilities:**
- Manage Solana keypairs
- Execute SPL token transfers
- Monitor transaction confirmations
- Handle retry logic for failed txs
- Report status back to Laravel API

**Example Structure:**
```
services/solana-processor/
├── src/
│   ├── wallet.ts          # Keypair management
│   ├── transfer.ts        # SPL token transfers
│   ├── monitor.ts         # Transaction monitoring
│   └── api.ts             # Express API endpoints
├── package.json
└── Dockerfile
```

### Step 2: Modify ExecutePayment Job

Update `app/Jobs/ExecutePayment.php` to call Solana service:

```php
public function handle(): void
{
    // ... existing validation ...
    
    try {
        DB::beginTransaction();
        
        // Debit sender wallet (off-chain)
        $senderWallet->debit($intent->amount);
        $debitTx = Transaction::create([...]);
        
        // Call Solana microservice
        $solanaResponse = Http::timeout(30)
            ->post(config('services.solana.url') . '/transfer', [
                'from_pubkey' => $senderWallet->solana_address,
                'to_pubkey' => $receiverWallet->solana_address,
                'amount' => $intent->amount,
                'token_mint' => config('services.solana.usdc_mint'),
                'reference' => $intent->reference,
            ]);
        
        if (!$solanaResponse->successful()) {
            throw new Exception('Solana transfer failed');
        }
        
        $signature = $solanaResponse->json('signature');
        
        // Store on-chain tx hash
        $debitTx->update(['external_tx' => $signature]);
        
        // Credit receiver wallet (off-chain)
        $receiverWallet->credit($intent->amount);
        $creditTx = Transaction::create([
            'external_tx' => $signature,
            // ... other fields
        ]);
        
        DB::commit();
        
        // Queue monitoring job to confirm tx
        MonitorSolanaTransaction::dispatch($signature, $intent->id)
            ->delay(now()->addSeconds(10));
            
    } catch (Exception $e) {
        DB::rollBack();
        // ... rollback logic
    }
}
```

### Step 3: Add Solana Address to Wallets

Create migration:
```bash
php artisan make:migration add_solana_address_to_wallets
```

```php
Schema::table('wallets', function (Blueprint $table) {
    $table->string('solana_address', 44)->nullable()->unique();
    $table->index('solana_address');
});
```

### Step 4: Create Transaction Monitor Job

```php
// app/Jobs/MonitorSolanaTransaction.php
class MonitorSolanaTransaction implements ShouldQueue
{
    public function handle(): void
    {
        $response = Http::get(config('services.solana.url') . '/status/' . $this->signature);
        
        $status = $response->json('status');
        
        if ($status === 'confirmed') {
            $this->intent->markAsCompleted();
        } elseif ($status === 'failed') {
            // Trigger refund process
            $this->intent->markAsFailed();
            RefundPayment::dispatch($this->intentId);
        } else {
            // Retry monitoring
            self::dispatch($this->signature, $this->intentId)
                ->delay(now()->addSeconds(30));
        }
    }
}
```

### Step 5: Configuration

Add to `config/services.php`:
```php
'solana' => [
    'url' => env('SOLANA_SERVICE_URL', 'http://localhost:3000'),
    'network' => env('SOLANA_NETWORK', 'devnet'),
    'usdc_mint' => env('SOLANA_USDC_MINT', 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'),
],
```

### Benefits of This Architecture

1. **Separation of Concerns**: Laravel handles business logic, Solana service handles blockchain
2. **Scalability**: Solana service can be scaled independently
3. **Reliability**: Failed blockchain txs don't crash the main app
4. **Testability**: Can mock Solana service in tests
5. **Flexibility**: Easy to swap blockchain providers

### Example Solana Service (Node.js)

```typescript
// services/solana-processor/src/transfer.ts
import { Connection, Keypair, Transaction } from '@solana/web3.js';
import { getOrCreateAssociatedTokenAccount, transfer } from '@solana/spl-token';

export async function transferUSDC(
  fromKeypair: Keypair,
  toPubkey: string,
  amount: number
): Promise<string> {
  const connection = new Connection(process.env.SOLANA_RPC_URL);
  
  // Get token accounts
  const fromTokenAccount = await getOrCreateAssociatedTokenAccount(
    connection,
    fromKeypair,
    USDC_MINT,
    fromKeypair.publicKey
  );
  
  // Execute transfer
  const signature = await transfer(
    connection,
    fromKeypair,
    fromTokenAccount.address,
    toTokenAccount.address,
    fromKeypair.publicKey,
    amount * 1e6 // USDC has 6 decimals
  );
  
  return signature;
}
```

## Security Considerations

1. **Database Locking**: Uses `lockForUpdate()` to prevent race conditions
2. **Transaction Safety**: All operations wrapped in DB transactions
3. **Authorization**: Validates user owns payment intent
4. **Balance Validation**: Double-checks balance before execution
5. **Idempotency**: Prevents duplicate processing of same intent
6. **Audit Trail**: Complete transaction history maintained

## Production Recommendations

1. **Queue Configuration**: Use Redis/SQS for queue backend
2. **Monitoring**: Set up alerts for failed payments
3. **Rate Limiting**: Implement rate limits on payment endpoints
4. **Webhook Support**: Add webhooks for payment status updates
5. **Multi-Currency**: Extend to support multiple currencies
6. **Fee Structure**: Implement transaction fees if needed
7. **Compliance**: Add KYC/AML checks before large transfers

## Next Steps

- [ ] Add webhook notifications for payment status changes
- [ ] Implement refund functionality
- [ ] Add payment history endpoints
- [ ] Create admin dashboard for payment monitoring
- [ ] Implement multi-currency support
- [ ] Add scheduled payment support
- [ ] Integrate with KYC service for large transactions
- [ ] Deploy Solana microservice for on-chain payments

## License

Part of the MyXen Foundation monorepo.
