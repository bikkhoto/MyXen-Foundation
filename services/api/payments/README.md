# Payments Engine Module

A comprehensive cryptocurrency payment system for the MyXen Foundation Laravel application, featuring blockchain integration via Solana, multi-wallet support, and robust transaction management.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Payment Flow](#payment-flow)
- [API Endpoints](#api-endpoints)
- [Database Schema](#database-schema)
- [Testing](#testing)
- [Idempotency](#idempotency)
- [Security](#security)
- [Troubleshooting](#troubleshooting)

## Overview

The Payments Engine module provides a complete solution for managing cryptocurrency transactions with blockchain integration. It handles wallet management, payment intent creation, asynchronous blockchain transfers, and comprehensive admin controls.

### Key Components

- **3 Database Migrations**: `wallets`, `transactions`, `payment_intents`
- **3 Models**: `Wallet`, `Transaction`, `PaymentIntent`
- **2 Controllers**: `PaymentsController`, `AdminPaymentsController`
- **1 Job**: `ExecutePaymentJob` (async blockchain integration)
- **2 Services**: `PaymentService`, `SolanaWorkerClient`
- **Routes**: User-facing and admin API endpoints
- **Tests**: Comprehensive test suite with HTTP mocking

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Payment Flow                            │
└─────────────────────────────────────────────────────────────┘

1. User Request
   POST /v1/payments/create-intent
   ↓
2. PaymentsController
   - Validates request
   - Checks wallet balance
   ↓
3. PaymentService::reserveFunds()
   - Creates PaymentIntent (UUID reference)
   - Debits wallet (fund reservation)
   - Creates pending Transaction
   ↓
4. User Execution
   POST /v1/payments/execute
   ↓
5. ExecutePaymentJob Dispatch
   - Marks intent as "ready"
   - Dispatches async job
   ↓
6. ExecutePaymentJob::handle()
   ┌─────────────────────────┐
   │ DB::beginTransaction()  │
   │                         │
   │ - Idempotency check     │
   │ - Verify balance        │
   │ - Mark "executing"      │
   │                         │
   │ Solana Worker Call      │
   │ POST /transfer          │
   │   {tokenMint, amount,   │
   │    from, to, requestId} │
   │                         │
   │ Success?                │
   │ ├─ Yes:                 │
   │ │  - Mark debit complete│
   │ │  - Credit receiver    │
   │ │  - Mark intent complete│
   │ │  - DB::commit()      │
   │ └─ No:                  │
   │    - Credit back funds  │
   │    - Mark failed        │
   │    - DB::rollBack()     │
   └─────────────────────────┘
```

## Features

### Core Capabilities

- ✅ **Multi-Wallet Management**: Support for multiple currencies per user
- ✅ **Blockchain Integration**: Solana worker for on-chain transfers
- ✅ **Idempotency**: UUID-based references prevent double-spend
- ✅ **Atomic Transactions**: Database transactions with automatic rollback
- ✅ **Async Processing**: Queue-based payment execution with retry logic
- ✅ **Precision Math**: 30-digit precision with 9 decimal places for crypto amounts
- ✅ **Admin Controls**: Logs, manual reconciliation, and refund capabilities
- ✅ **Security**: Fund reservation, balance verification, owner validation

### Payment Statuses

**Payment Intent Statuses:**
- `created`: Intent created, funds reserved
- `ready`: Ready for execution (job dispatched)
- `executing`: Blockchain transfer in progress
- `completed`: Transfer successful
- `failed`: Transfer failed (funds released)
- `cancelled`: User-cancelled (funds released)

**Transaction Statuses:**
- `pending`: Awaiting completion
- `completed`: Successfully processed
- `failed`: Processing failed

## Installation

### 1. Run Migrations

```bash
php artisan migrate
```

This creates three tables:
- `wallets` - User cryptocurrency wallets
- `transactions` - Transaction history
- `payment_intents` - Payment workflow tracking

### 2. Configure Environment

Add the following to your `.env` file:

```env
# Solana Worker Configuration
SOLANA_WORKER_URL=http://localhost:8080
SOLANA_WORKER_TIMEOUT=30

# Payment Configuration
TOKEN_MINT=YourSolanaTokenMintAddress
DEFAULT_CURRENCY=MYXN
```

### 3. Create Configuration File

Create `config/payments.php`:

```php
<?php

return [
    'solana_worker_url' => env('SOLANA_WORKER_URL', 'http://localhost:8080'),
    'solana_worker_timeout' => env('SOLANA_WORKER_TIMEOUT', 30),
    'token_mint' => env('TOKEN_MINT'),
    'default_currency' => env('DEFAULT_CURRENCY', 'MYXN'),
];
```

### 4. Configure Queue Worker

Ensure your queue worker is running:

```bash
php artisan queue:work
```

## Configuration

### Solana Worker Setup

The Solana worker is an external HTTP service that handles blockchain transactions. It should expose a `/transfer` endpoint:

**Expected Request:**
```json
{
  "tokenMint": "YourTokenMintAddress",
  "amount": "100.500000000",
  "fromTokenAccount": "sender-address",
  "toTokenAccount": "receiver-address",
  "requestId": "unique-uuid-reference"
}
```

**Expected Response (Success):**
```json
{
  "success": true,
  "txSignature": "solana-transaction-signature"
}
```

**Expected Response (Failure):**
```json
{
  "success": false,
  "error": "Error description"
}
```

## Payment Flow

### Creating a Payment Intent

```bash
curl -X POST http://localhost:8000/api/v1/payments/create-intent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 100.5,
    "currency": "MYXN",
    "receiver_address": "receiver-token-account-address",
    "memo": "Payment for services"
  }'
```

**Response:**
```json
{
  "success": true,
  "intent_id": 1,
  "reference": "550e8400-e29b-41d4-a716-446655440000",
  "status": "created",
  "amount": "100.500000000",
  "currency": "MYXN"
}
```

### Executing a Payment

```bash
curl -X POST http://localhost:8000/api/v1/payments/execute \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "intent_id": 1
  }'
```

**Response:**
```json
{
  "success": true,
  "message": "Payment execution started.",
  "intent_id": 1,
  "status": "ready"
}
```

## API Endpoints

### User Endpoints

**`POST /api/v1/payments/create-intent`**
- **Auth**: `auth:sanctum`
- **Body**: `{amount, currency, receiver_address, memo?}`
- **Returns**: Payment intent with UUID reference

**`POST /api/v1/payments/execute`**
- **Auth**: `auth:sanctum`
- **Body**: `{intent_id}`
- **Returns**: Execution confirmation
- **Action**: Dispatches async job for blockchain transfer

### Admin Endpoints

**`GET /api/v1/admin/payments/logs`**
- **Auth**: `auth:admin`
- **Query Params**: `per_page`, `status`, `wallet_id`
- **Returns**: Paginated transaction logs

**`POST /api/v1/admin/payments/{id}/reconcile`**
- **Auth**: `auth:admin`
- **Body**: `{external_tx?, notes?}`
- **Returns**: Reconciliation confirmation
- **Action**: Manually marks intent as completed

**`POST /api/v1/admin/payments/{id}/refund`**
- **Auth**: `auth:admin`
- **Body**: `{reason?}`
- **Returns**: Refund confirmation
- **Action**: Credits funds back to user wallet

## Database Schema

### Wallets Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint (nullable) | Foreign key to users |
| address | string (nullable) | Blockchain wallet address |
| currency | string | Currency code (default: MYXN) |
| balance | decimal(30,9) | Current balance |
| status | enum | active, disabled |
| metadata | json (nullable) | Additional data |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes**: user_id, address, status

### Transactions Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| wallet_id | bigint | Foreign key to wallets |
| counterparty_wallet_id | bigint (nullable) | FK to wallets |
| amount | decimal(30,9) | Transaction amount |
| type | enum | debit, credit |
| status | enum | pending, completed, failed |
| external_tx | string (nullable) | Blockchain tx signature |
| reference | string (unique, nullable) | Idempotency reference |
| memo | string (nullable) | Transaction note |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes**: wallet_id, counterparty_wallet_id, status, type, external_tx, created_at

### Payment Intents Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users |
| wallet_id | bigint | Foreign key to wallets |
| amount | decimal(30,9) | Payment amount |
| currency | string | Currency code |
| receiver_wallet_address | string | Receiver's address |
| status | enum | created, ready, executing, completed, failed, cancelled |
| meta | json (nullable) | Metadata (includes UUID reference) |
| created_at | timestamp | |
| updated_at | timestamp | |

**Indexes**: user_id, wallet_id, status, created_at

## Testing

Run the test suite:

```bash
php artisan test tests/Feature/Payments/PaymentsTest.php
```

### Test Coverage

1. ✅ `test_create_intent_requires_auth` - Authentication validation
2. ✅ `test_create_intent_reserves_funds` - Fund reservation and DB checks
3. ✅ `test_execute_dispatches_job_and_completes_on_worker_success` - Full payment flow with HTTP mocking
4. ✅ `test_execute_rolls_back_on_worker_failure` - Rollback mechanism
5. ✅ `test_admin_can_view_payment_logs` - Admin logs endpoint
6. ✅ `test_admin_reconcile_works` - Manual reconciliation

### Testing Features

- **Http::fake()**: Mocks Solana worker responses
- **Bus::fake()**: Tests job dispatching
- **RefreshDatabase**: Clean test environment
- **Factory Support**: Automated test data creation

## Idempotency

The system implements idempotency to prevent duplicate transactions:

### UUID Reference Generation

Every `PaymentIntent` automatically generates a unique UUID reference on creation:

```php
// Stored in meta['reference']
"reference" => "550e8400-e29b-41d4-a716-446655440000"
```

### Duplicate Transaction Prevention

Before executing a payment, the job checks for existing completed transactions:

```php
$existingCompleted = Transaction::where('reference', $intent->getReference())
    ->where('status', 'completed')
    ->exists();

if ($existingCompleted) {
    // Skip execution, mark intent as completed
    return;
}
```

### Solana Worker Integration

The UUID reference is passed to the Solana worker as `requestId`, allowing the worker to implement its own idempotency checks.

## Security

### Double-Spend Prevention

1. **Fund Reservation**: Funds are debited during intent creation
2. **Balance Verification**: Job verifies balance before blockchain call
3. **Idempotency Checks**: Prevents duplicate processing
4. **Atomic Transactions**: Database transactions with rollback

### Access Control

- **User Endpoints**: `auth:sanctum` middleware
- **Admin Endpoints**: `auth:admin` middleware
- **Ownership Validation**: Users can only execute their own intents

### Error Handling

- **Database Rollback**: Automatic on failure
- **Fund Release**: Credits back on failure
- **Retry Mechanism**: Exponential backoff (5s, 30s, 120s)
- **Logging**: Comprehensive error tracking

## Troubleshooting

### Common Issues

**Issue**: Payment stuck in "executing" status

**Solution**: Check Solana worker availability and logs. Use admin reconcile endpoint to manually complete.

```bash
curl -X POST http://localhost:8000/api/v1/admin/payments/1/reconcile \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -d '{"external_tx": "manual-tx-sig", "notes": "Manually reconciled"}'
```

---

**Issue**: Insufficient balance errors

**Solution**: Verify wallet balance and ensure funds aren't locked in pending intents.

```sql
SELECT * FROM wallets WHERE user_id = ?;
SELECT SUM(amount) FROM transactions 
WHERE wallet_id = ? AND status = 'pending' AND type = 'debit';
```

---

**Issue**: Solana worker connection failures

**Solution**: Check `SOLANA_WORKER_URL` in `.env` and verify worker health:

```bash
curl http://localhost:8080/health
```

### Logs

Check Laravel logs for payment execution details:

```bash
tail -f storage/logs/laravel.log | grep "Payment"
```

### Queue Monitoring

Monitor failed jobs:

```bash
php artisan queue:failed
```

Retry failed jobs:

```bash
php artisan queue:retry all
```

---

## License

This module is part of the MyXen Foundation Laravel application and follows the same license.
