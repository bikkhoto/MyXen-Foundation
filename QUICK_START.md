# Quick Start Guide

Get the Presale + Vesting system running in 10 minutes.

## Prerequisites

Install these tools if you haven't already:

```bash
# Rust
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh

# Solana CLI
sh -c "$(curl -sSfL https://release.solana.com/v1.17.0/install)"

# Anchor CLI
cargo install --git https://github.com/coral-xyz/anchor --tag v0.29.0 anchor-cli --locked

# Node.js (if needed)
# Visit https://nodejs.org/

# PHP 8.2+ with libsodium (if needed)
# Visit https://www.php.net/downloads
```

## Step 1: Generate Voucher Signer Keypair (2 min)

```bash
cd "MyXen Foundation (Laravel)"

# Run the generation script
./scripts/generate-voucher-keypair.sh

# Follow prompts (press Enter for default path)
# This creates: ~/.myxen/keys/voucher-signer.json
```

## Step 2: Build & Deploy Anchor Program (3 min)

```bash
cd anchor-presale

# Install dependencies
yarn install

# Build the program
anchor build

# Configure for devnet
solana config set --url https://api.devnet.solana.com

# Create keypair if you don't have one
solana-keygen new --outfile ~/.config/solana/id.json

# Get some SOL for deployment
solana airdrop 2

# Deploy
anchor deploy --provider.cluster devnet

# Copy the Program ID from output
# Example: FsJ3A3u2vn5cTVofAjvy6y5kwABJAqYWpe4975bi2epH
```

## Step 3: Update Program ID (1 min)

Replace `11111111111111111111111111111111` with your actual Program ID in:

1. **programs/anchor-presale/src/lib.rs**:
   ```rust
   declare_id!("YOUR_PROGRAM_ID_HERE");
   ```

2. **Anchor.toml**:
   ```toml
   [programs.devnet]
   anchor_presale = "YOUR_PROGRAM_ID_HERE"
   ```

3. Rebuild and redeploy:
   ```bash
   anchor build
   anchor deploy --provider.cluster devnet
   ```

## Step 4: Configure Laravel Backend (2 min)

```bash
cd ../services/api

# Copy environment file
cp .env.example .env

# Add this line to .env:
echo "VOUCHER_SIGNER_KEYPATH=$HOME/.myxen/keys/voucher-signer.json" >> .env

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate

# Start server
php artisan serve
```

Laravel is now running at: http://localhost:8000

## Step 5: Test the System (2 min)

### Test Anchor Program

```bash
cd anchor-presale

# Run tests (starts local validator automatically)
anchor test
```

### Test Laravel Backend

```bash
cd ../services/api

# Run tests
php artisan test tests/Feature/Services/Sale/VoucherTest.php
```

## Step 6: Initialize a Sale (Optional)

Create a test sale using the TypeScript client:

```bash
cd anchor-presale

# Create a script: scripts/init-sale.ts
# (See README for full example)

# Run it:
ts-node scripts/init-sale.ts
```

## Step 7: Issue a Voucher via API

```bash
# First, create an admin user or login
# (Adjust credentials as needed)

curl -X POST http://localhost:8000/api/v1/sale/whitelist \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{
    "buyer_pubkey": "9WzDXwBbmkg8ZTbNMqUxvQRAyrZzDsGYdLVL9zYtAWWM",
    "sale_pubkey": "YOUR_SALE_CONFIG_PDA",
    "max_allocation": 10000,
    "expiry_ts": '$(($(date +%s) + 3600))'
  }'
```

## Common Commands

```bash
# Build Anchor program
anchor build

# Test Anchor program
anchor test

# Deploy to devnet
anchor deploy --provider.cluster devnet

# Run Laravel tests
php artisan test

# Start Laravel server
php artisan serve

# Run migrations
php artisan migrate

# Check Solana balance
solana balance

# Airdrop SOL (devnet)
solana airdrop 2

# View transaction on explorer
# https://explorer.solana.com/tx/TRANSACTION_SIGNATURE?cluster=devnet
```

## Troubleshooting

### "Voucher signer keypair not found"
```bash
# Regenerate keypair
./scripts/generate-voucher-keypair.sh

# Ensure VOUCHER_SIGNER_KEYPATH is in .env
echo "VOUCHER_SIGNER_KEYPATH=$HOME/.myxen/keys/voucher-signer.json" >> services/api/.env
```

### "Program Id not found"
```bash
# Redeploy the program
cd anchor-presale
anchor deploy --provider.cluster devnet

# Update the Program ID in all files (see Step 3)
```

### "Insufficient funds"
```bash
# Airdrop more SOL (devnet only)
solana airdrop 2

# Check balance
solana balance
```

### "Test failures"
```bash
# Make sure local validator is running (Anchor tests)
solana-test-validator

# Make sure Laravel migrations are run
cd services/api
php artisan migrate --env=testing
```

## Next Steps

1. **Read the full README**: `anchor-presale/README.md`
2. **Understand the architecture**: See architecture diagram in README
3. **Customize the sale**: Adjust parameters in `initialize_sale`
4. **Build the frontend**: Integrate `app/BuyTokens.tsx` into your React app
5. **Add KYC**: Implement KYC middleware for voucher issuance
6. **Deploy to production**: Follow production security checklist

## Resources

- **Full Documentation**: `anchor-presale/README.md`
- **Implementation Summary**: `PRESALE_IMPLEMENTATION_SUMMARY.md`
- **Anchor Docs**: https://www.anchor-lang.com/
- **Solana Docs**: https://docs.solana.com/
- **Laravel Docs**: https://laravel.com/docs

---

**You're all set!** 🚀

The presale system is ready to use. See README.md for detailed usage instructions and security best practices.
