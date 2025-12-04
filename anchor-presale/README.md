# Solana Presale + Vesting System

A comprehensive token presale and vesting system built with Solana Anchor, TypeScript, Laravel, and React. This system implements whitelist-based token sales using cryptographically signed vouchers and linear vesting schedules with cliff periods.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                     Solana Presale System                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌─────────────┐      ┌──────────────┐      ┌───────────────┐  │
│  │   React UI  │─────▶│ Laravel API  │◀────▶│  Admin Panel  │  │
│  │  (Phantom)  │      │  (Vouchers)  │      │  (Whitelist)  │  │
│  └──────┬──────┘      └──────┬───────┘      └───────────────┘  │
│         │                    │                                   │
│         │ Request Voucher    │ Ed25519 Signing                   │
│         ▼                    ▼                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │           Solana Anchor Program (On-Chain)              │   │
│  │  • buy_with_voucher (verify ed25519 signature)          │   │
│  │  • create_vesting (owner creates vesting schedules)     │   │
│  │  • claim_vested (beneficiaries claim unlocked tokens)   │   │
│  │  • revoke_vesting (owner revokes unvested tokens)       │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

### Key Components

1. **Anchor Program (Rust)**: On-chain smart contract handling presale logic, voucher verification, and vesting
2. **Laravel Backend (PHP)**: Voucher signing service with ed25519, admin whitelist management
3. **React Frontend (TypeScript)**: User interface for wallet connection and token purchase
4. **TypeScript Tests**: Comprehensive integration tests for all program instructions

## Features

### Presale Features
- **Voucher-Based Whitelist**: Only users with valid signed vouchers can purchase
- **Ed25519 Signature Verification**: Backend signs vouchers, on-chain program verifies authenticity
- **Replay Protection**: Each voucher has a unique nonce to prevent reuse
- **Time-Bound Vouchers**: Vouchers expire after a specified timestamp
- **Allocation Limits**: Each voucher specifies maximum tokens a buyer can purchase
- **SOL Payment**: Buyers pay in SOL (USDC/SPL token support commented in code)

### Vesting Features
- **Linear Vesting**: Tokens unlock linearly over a specified duration
- **Cliff Periods**: Optional cliff period before any tokens unlock
- **Claimable Tokens**: Beneficiaries can claim vested tokens at any time
- **Revocable Vesting**: Owner can revoke vesting and return unvested tokens
- **Multiple Beneficiaries**: Create separate vesting schedules for different buyers

### Security Features
- **PDA (Program Derived Addresses)**: Secure account derivation using program seeds
- **Owner-Only Actions**: Critical operations restricted to sale owner
- **Timestamp Validation**: Sale start/end times enforced
- **Overflow Protection**: Safe math operations for all calculations
- **Signature Verification**: On-chain ed25519 signature verification for vouchers

## Prerequisites

### Required Software
- **Rust**: v1.70+ ([Install](https://www.rust-lang.org/tools/install))
- **Anchor CLI**: v0.29.0 (`cargo install --git https://github.com/coral-xyz/anchor --tag v0.29.0 anchor-cli`)
- **Solana CLI**: v1.17+ ([Install](https://docs.solana.com/cli/install-solana-cli-tools))
- **Node.js**: v18+ ([Install](https://nodejs.org/))
- **Yarn**: v1.22+ (`npm install -g yarn`)
- **PHP**: v8.2+ with libsodium extension ([Install](https://www.php.net/downloads))
- **Composer**: v2.0+ ([Install](https://getcomposer.org/))
- **Laravel**: v10.x

### Development Tools
- **Phantom Wallet**: Browser extension for testing ([Install](https://phantom.app/))
- **Solana Explorer**: For transaction verification ([devnet](https://explorer.solana.com/?cluster=devnet))

## Project Structure

```
.
├── anchor-presale/                 # Anchor workspace
│   ├── programs/
│   │   └── anchor-presale/
│   │       ├── src/
│   │       │   └── lib.rs          # Main program logic
│   │       └── Cargo.toml
│   ├── tests/
│   │   └── presale.test.ts         # Integration tests
│   ├── app/
│   │   └── BuyTokens.tsx           # React component
│   ├── target/
│   │   └── idl/                    # Generated IDL
│   ├── Anchor.toml                 # Anchor config
│   └── package.json
│
└── services/api/                   # Laravel backend
    ├── app/
    │   ├── Http/Controllers/Services/Sale/Controllers/
    │   │   └── VoucherController.php
    │   └── Models/Models/
    │       └── SaleVoucher.php
    ├── database/
    │   ├── migrations/
    │   │   └── *_create_sale_vouchers_table.php
    │   └── factories/
    │       └── SaleVoucherFactory.php
    ├── routes/
    │   └── api.php
    └── tests/Feature/Services/Sale/
        └── VoucherTest.php
```

## Build & Deploy

### 1. Build Anchor Program

```bash
cd anchor-presale

# Install dependencies
yarn install

# Build the program
anchor build

# This generates:
# - target/deploy/anchor_presale.so (compiled program)
# - target/idl/anchor_presale.json (IDL for frontend)
```

### 2. Deploy to Devnet

```bash
# Configure Solana CLI for devnet
solana config set --url https://api.devnet.solana.com

# Create a keypair for deployment (if you don't have one)
solana-keygen new --outfile ~/.config/solana/id.json

# Airdrop SOL for deployment fees
solana airdrop 2

# Deploy the program
anchor deploy --provider.cluster devnet

# Output will show program ID, e.g.:
# Program Id: FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH
```

### 3. Update Program ID

After deployment, update the program ID in:

**programs/anchor-presale/src/lib.rs**:
```rust
declare_id!("FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH"); // Replace with your actual ID
```

**Anchor.toml**:
```toml
[programs.devnet]
anchor_presale = "FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH"
```

**app/BuyTokens.tsx**:
```typescript
const PROGRAM_ID = new PublicKey("FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH");
```

Rebuild after updating:
```bash
anchor build
anchor deploy --provider.cluster devnet
```

## Laravel Backend Setup

### 1. Generate Voucher Signer Keypair

The Laravel backend needs an ed25519 keypair to sign vouchers:

```bash
# Create directory for keys
mkdir -p ~/.myxen/keys

# Generate keypair using Solana CLI
solana-keygen new --outfile ~/.myxen/keys/voucher-signer.json --no-bip39-passphrase

# Secure the keypair
chmod 600 ~/.myxen/keys/voucher-signer.json

# IMPORTANT: Backup this keypair! If lost, all issued vouchers become invalid.
```

### 2. Configure Laravel Environment

Add to `.env`:
```env
# Voucher Signer Keypair Path
VOUCHER_SIGNER_KEYPATH=/home/youruser/.myxen/keys/voucher-signer.json

# Frontend CORS
FRONTEND_URL=http://localhost:3000
```

### 3. Run Migrations

```bash
cd services/api

# Run migrations to create sale_vouchers table
php artisan migrate

# Seed admin users (if needed)
php artisan db:seed
```

### 4. Start Laravel Server

```bash
php artisan serve
# Server starts at http://localhost:8000
```

### 5. Test Voucher Endpoint

```bash
# Login as admin to get token (adjust endpoint as needed)
curl -X POST http://localhost:8000/api/v1/admin/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@test.com", "password": "password"}'

# Issue a voucher
curl -X POST http://localhost:8000/api/v1/sale/whitelist \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{
    "buyer_pubkey": "9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM",
    "sale_pubkey": "FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH",
    "max_allocation": 10000,
    "expiry_ts": 1735689600
  }'
```

## Testing

### Anchor Tests (Localnet)

```bash
cd anchor-presale

# Start local validator in separate terminal
solana-test-validator

# Run tests against local validator
anchor test --skip-local-validator

# Or run with local validator auto-start
anchor test
```

### Anchor Tests (Devnet)

```bash
# Update Anchor.toml cluster to devnet
anchor test --skip-local-validator --provider.cluster devnet
```

### Laravel Tests

```bash
cd services/api

# Run all tests
php artisan test

# Run only voucher tests
php artisan test tests/Feature/Services/Sale/VoucherTest.php

# Run with coverage
php artisan test --coverage
```

## Frontend Setup

### 1. Install Dependencies

```bash
cd anchor-presale/app

# Install React and Solana libraries
npm install \
  react react-dom \
  @solana/wallet-adapter-react \
  @solana/wallet-adapter-react-ui \
  @solana/wallet-adapter-wallets \
  @solana/web3.js \
  @coral-xyz/anchor \
  @solana/spl-token
```

### 2. Configure Environment

Create `.env`:
```env
REACT_APP_BACKEND_URL=http://localhost:8000
REACT_APP_SOLANA_RPC=https://api.devnet.solana.com
REACT_APP_PROGRAM_ID=FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH
```

### 3. Integrate BuyTokens Component

```typescript
// In your main App.tsx
import { ConnectionProvider, WalletProvider } from '@solana/wallet-adapter-react';
import { WalletAdapterNetwork } from '@solana/wallet-adapter-base';
import { PhantomWalletAdapter } from '@solana/wallet-adapter-wallets';
import { WalletModalProvider } from '@solana/wallet-adapter-react-ui';
import { clusterApiUrl } from '@solana/web3.js';
import BuyTokens from './BuyTokens';

// Import wallet adapter CSS
import '@solana/wallet-adapter-react-ui/styles.css';

function App() {
  const network = WalletAdapterNetwork.Devnet;
  const endpoint = useMemo(() => clusterApiUrl(network), [network]);
  const wallets = useMemo(() => [new PhantomWalletAdapter()], []);

  return (
    <ConnectionProvider endpoint={endpoint}>
      <WalletProvider wallets={wallets} autoConnect>
        <WalletModalProvider>
          <BuyTokens />
        </WalletModalProvider>
      </WalletProvider>
    </ConnectionProvider>
  );
}
```

## Usage Guide

### For Sale Owners

#### 1. Initialize a Sale

```typescript
// Using Anchor TypeScript client
const tx = await program.methods
  .initializeSale(
    new BN(1_000_000_000), // price per token (lamports)
    new BN(Math.floor(Date.now() / 1000)), // start timestamp
    new BN(Math.floor(Date.now() / 1000) + 86400 * 7), // end timestamp (7 days)
    new BN(1_000_000_000_000) // total tokens allocated
  )
  .accounts({
    saleConfig: saleConfigPDA,
    tokenMint: tokenMint,
    treasury: treasury.publicKey,
    owner: owner.publicKey,
    systemProgram: SystemProgram.programId,
  })
  .rpc();
```

#### 2. Whitelist Buyers (Admin Panel)

Admins use the Laravel backend to issue vouchers:
1. Login to admin panel
2. Navigate to "Presale Management"
3. Enter buyer's Solana wallet address
4. Set max allocation and expiry
5. Click "Issue Voucher"

#### 3. Create Vesting Schedules

After buyers purchase, create vesting schedules:

```typescript
const tx = await program.methods
  .createVesting(
    new BN(1_000_000_000), // total vested amount
    new BN(Math.floor(Date.now() / 1000)), // vesting start time
    new BN(86400 * 30), // cliff duration (30 days)
    new BN(86400 * 365), // total vesting duration (1 year)
    true // revocable
  )
  .accounts({
    saleConfig: saleConfigPDA,
    vesting: vestingPDA,
    beneficiary: buyer.publicKey,
    owner: owner.publicKey,
    systemProgram: SystemProgram.programId,
  })
  .rpc();
```

### For Buyers

#### 1. Connect Wallet

Open the presale frontend and click "Connect Wallet"

#### 2. Purchase Tokens

1. Enter sale configuration public key
2. Enter desired token amount
3. Click "Purchase Tokens"
4. Approve transaction in Phantom wallet

The flow:
- Frontend requests voucher from Laravel backend
- Backend verifies buyer is whitelisted
- Backend signs voucher with ed25519
- Frontend calls `buy_with_voucher` with voucher + signature
- On-chain program verifies signature and processes purchase

#### 3. Claim Vested Tokens

After cliff period, claim unlocked tokens:

```typescript
const tx = await program.methods
  .claimVested()
  .accounts({
    vesting: vestingPDA,
    tokenMint: tokenMint,
    vestingVault: vestingVault,
    beneficiaryTokenAccount: beneficiaryTokenAccount,
    beneficiary: buyer.publicKey,
    tokenProgram: TOKEN_PROGRAM_ID,
  })
  .rpc();
```

## Security Considerations

### Critical Security Points

1. **Voucher Signer Keypair**
   - Store securely, never commit to git
   - Use hardware security module (HSM) in production
   - Rotate keys if compromised
   - Backup to secure offline storage

2. **Nonce Uniqueness**
   - Laravel generates unique nonce per voucher
   - On-chain program prevents replay by checking BuyerEscrow existence
   - Database unique constraint on nonce column

3. **Signature Verification**
   - On-chain ed25519 verification ensures voucher authenticity
   - Message format must match exactly: buyer (32) + sale (32) + max_allocation (8 LE) + nonce (8 LE) + expiry_ts (8 LE)

4. **Admin Access Control**
   - Voucher issuance requires admin authentication
   - Laravel middleware: `auth:admin`
   - Consider adding KYC verification: `kyc.approved` middleware

5. **Rate Limiting**
   - Implement rate limiting on voucher endpoint
   - Prevent abuse of whitelist system

### Production Recommendations

- **Use HTTPS**: All API calls must be over HTTPS
- **Environment Variables**: Never hardcode secrets
- **Key Management**: Use AWS KMS, Azure Key Vault, or similar
- **Monitoring**: Log all voucher issuances and purchases
- **Auditing**: Track who issued vouchers and when
- **Revocation**: Implement voucher revocation mechanism if needed

## Troubleshooting

### Common Issues

#### 1. Signature Verification Fails

**Error**: `VoucherError::InvalidSignature`

**Causes**:
- Message serialization mismatch between PHP and Rust
- Incorrect public key encoding
- Signature bytes order incorrect

**Solution**:
```bash
# Verify message format in PHP:
# buyer (32 bytes) + sale (32 bytes) + max_allocation (8 LE) + nonce (8 LE) + expiry_ts (8 LE)

# Check signature length:
$signature = sodium_crypto_sign_detached($message, $secretKey);
echo strlen($signature); // Should be 64
```

#### 2. Voucher Expired

**Error**: `VoucherError::VoucherExpired`

**Cause**: `expiry_ts` is in the past

**Solution**: Ensure frontend requests voucher with future expiry:
```typescript
expiry_ts: Math.floor(Date.now() / 1000) + 3600 // 1 hour from now
```

#### 3. Exceeds Allocation

**Error**: `VoucherError::ExceedsAllocation`

**Cause**: Buyer trying to purchase more than `max_allocation`

**Solution**: Frontend should validate amount before submission:
```typescript
if (amount > voucher.max_allocation) {
  throw new Error(`Maximum allocation is ${voucher.max_allocation}`);
}
```

#### 4. Keypair Not Found

**Error**: `Voucher signer keypair not found`

**Cause**: `VOUCHER_SIGNER_KEYPATH` not configured or file missing

**Solution**:
```bash
# Generate keypair
solana-keygen new --outfile ~/.myxen/keys/voucher-signer.json

# Update .env
VOUCHER_SIGNER_KEYPATH=/home/youruser/.myxen/keys/voucher-signer.json
```

#### 5. Insufficient SOL for Transaction

**Error**: `0x1` (InsufficientFunds)

**Cause**: Buyer doesn't have enough SOL to pay

**Solution**: Ensure buyer has enough SOL:
```bash
# Check balance
solana balance BUYER_PUBKEY

# Airdrop on devnet
solana airdrop 2 BUYER_PUBKEY
```

## API Reference

### Laravel Endpoints

#### POST `/api/v1/sale/whitelist`

Issue a signed voucher for a whitelisted buyer.

**Authentication**: Admin token required

**Request**:
```json
{
  "buyer_pubkey": "9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM",
  "sale_pubkey": "FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH",
  "max_allocation": 10000,
  "expiry_ts": 1735689600
}
```

**Response** (201):
```json
{
  "success": true,
  "voucher": {
    "buyer": "9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM",
    "sale": "FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH",
    "max_allocation": 10000,
    "nonce": 1702934567890123,
    "expiry_ts": 1735689600
  },
  "signature": "base64-encoded-64-byte-signature",
  "signer_pubkey": "base58-encoded-public-key"
}
```

#### GET `/api/v1/sale/vouchers/{buyer_pubkey}`

Retrieve all vouchers issued to a buyer.

**Authentication**: None (public read)

**Response** (200):
```json
{
  "success": true,
  "vouchers": [
    {
      "id": 1,
      "buyer_pubkey": "9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM",
      "sale_pubkey": "FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH",
      "max_allocation": 10000,
      "nonce": 1702934567890123,
      "expiry_ts": 1735689600,
      "signature": "base64-signature",
      "issued_at": "2024-12-03T16:30:00Z"
    }
  ]
}
```

### Anchor Instructions

#### `initialize_sale`

Create a new token sale configuration.

**Accounts**:
- `sale_config` (init): Sale configuration PDA
- `token_mint`: SPL token mint
- `treasury`: Treasury to receive SOL/tokens
- `owner`: Sale owner (signer)
- `system_program`: System program

**Args**:
- `price_lamports_per_token: u64`: Price per token in lamports
- `start_ts: i64`: Sale start Unix timestamp
- `end_ts: i64`: Sale end Unix timestamp
- `total_allocated: u64`: Total tokens allocated for sale

#### `buy_with_voucher`

Purchase tokens using a signed voucher.

**Accounts**:
- `sale_config`: Sale configuration PDA
- `buyer_escrow` (init): Buyer's escrow PDA
- `buyer`: Buyer wallet (signer)
- `treasury`: Treasury to receive payment
- `voucher_signer`: Voucher signer public key
- `system_program`: System program

**Args**:
- `max_allocation: u64`: Max allocation from voucher
- `nonce: u64`: Unique nonce from voucher
- `expiry_ts: i64`: Expiry timestamp from voucher
- `signature: [u8; 64]`: Ed25519 signature
- `lamports: u64`: Amount to spend

#### `create_vesting`

Create a vesting schedule for a buyer.

**Accounts**:
- `sale_config`: Sale configuration PDA
- `vesting` (init): Vesting PDA
- `beneficiary`: Beneficiary wallet
- `owner`: Sale owner (signer)
- `system_program`: System program

**Args**:
- `total_amount: u64`: Total vested tokens
- `start_ts: i64`: Vesting start timestamp
- `cliff_seconds: u64`: Cliff duration in seconds
- `duration_seconds: u64`: Total vesting duration
- `revocable: bool`: Whether vesting is revocable

#### `claim_vested`

Claim vested tokens.

**Accounts**:
- `vesting`: Vesting PDA
- `token_mint`: SPL token mint
- `vesting_vault`: Vesting token vault
- `beneficiary_token_account`: Beneficiary's token account
- `beneficiary`: Beneficiary wallet (signer)
- `token_program`: SPL Token program

#### `revoke_vesting`

Revoke vesting and return unvested tokens.

**Accounts**:
- `sale_config`: Sale configuration PDA
- `vesting`: Vesting PDA (must be revocable)
- `token_mint`: SPL token mint
- `vesting_vault`: Vesting token vault
- `treasury_token_account`: Treasury token account
- `owner`: Sale owner (signer)
- `token_program`: SPL Token program

## Development Roadmap

### Phase 1: Core Implementation ✅
- [x] Anchor program with all instructions
- [x] Laravel voucher signing backend
- [x] React frontend component
- [x] Comprehensive tests
- [x] Documentation

### Phase 2: Enhancements (Planned)
- [ ] USDC/SPL token payment support
- [ ] Multi-tiered sale rounds
- [ ] Whitelist CSV upload
- [ ] Admin dashboard UI
- [ ] Real-time sale statistics
- [ ] Email notifications for vesting claims

### Phase 3: Advanced Features (Future)
- [ ] Merkle tree whitelist (gas optimization)
- [ ] Referral system
- [ ] Automated vesting creation after purchase
- [ ] Multi-sig treasury support
- [ ] Governance integration
- [ ] Mobile app (React Native)

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Commit changes: `git commit -am 'Add your feature'`
4. Push to branch: `git push origin feature/your-feature`
5. Submit a pull request

### Coding Standards
- **Rust**: Follow official Rust style guide
- **TypeScript**: Use Prettier and ESLint
- **PHP**: Follow PSR-12 coding standards
- **Tests**: Write tests for all new features

## License

This project is licensed under the MIT License. See LICENSE file for details.

## Support

For issues, questions, or feature requests:
- **GitHub Issues**: [Open an issue](https://github.com/your-org/presale-vesting/issues)
- **Documentation**: [Wiki](https://github.com/your-org/presale-vesting/wiki)
- **Email**: support@yourproject.com

## Acknowledgments

- **Solana Foundation**: For the blockchain infrastructure
- **Anchor Framework**: For the excellent Rust framework
- **Laravel Community**: For the powerful PHP framework
- **Phantom Wallet**: For the browser wallet integration

---

**Built with ❤️ for the Solana ecosystem**
