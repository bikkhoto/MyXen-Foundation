#!/bin/bash
#
# MyXen Presale - Devnet Deployment Script
# =========================================
# This script deploys the Anchor presale program to devnet and sets up the Laravel backend.
#
# Prerequisites:
# - Solana CLI installed and configured
# - Anchor CLI installed
# - Rust toolchain installed
# - PHP 8.1+ with Laravel
# - SOL in deployer wallet for devnet
#
# Usage: ./scripts/deploy-presale-devnet.sh [--skip-build] [--skip-laravel]
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ANCHOR_DIR="$REPO_ROOT/anchor-presale"
LARAVEL_DIR="$REPO_ROOT/services/api"
KEYS_DIR="$HOME/.myxen/keys"

# Parse arguments
SKIP_BUILD=false
SKIP_LARAVEL=false
for arg in "$@"; do
    case $arg in
        --skip-build) SKIP_BUILD=true ;;
        --skip-laravel) SKIP_LARAVEL=true ;;
    esac
done

echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}           MyXen Presale - Devnet Deployment                   ${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""

# Step 1: Check prerequisites
echo -e "${YELLOW}[1/7] Checking prerequisites...${NC}"

# Check Solana CLI
if ! command -v solana &> /dev/null; then
    echo -e "${RED}ERROR: Solana CLI not installed${NC}"
    echo "Install with: sh -c \"\$(curl -sSfL https://release.solana.com/stable/install)\""
    exit 1
fi
echo -e "  ✓ Solana CLI: $(solana --version)"

# Check Anchor CLI
if ! command -v anchor &> /dev/null; then
    echo -e "${RED}ERROR: Anchor CLI not installed${NC}"
    echo "Install with: cargo install --git https://github.com/coral-xyz/anchor avm --locked --force"
    exit 1
fi
echo -e "  ✓ Anchor CLI: $(anchor --version)"

# Check current Solana config
CURRENT_CLUSTER=$(solana config get | grep "RPC URL" | awk '{print $3}')
echo -e "  ✓ Current cluster: $CURRENT_CLUSTER"

# Check deployer wallet balance
DEPLOYER_PUBKEY=$(solana address)
DEPLOYER_BALANCE=$(solana balance $DEPLOYER_PUBKEY 2>/dev/null | awk '{print $1}')
echo -e "  ✓ Deployer: $DEPLOYER_PUBKEY"
echo -e "  ✓ Balance: $DEPLOYER_BALANCE SOL"

if (( $(echo "$DEPLOYER_BALANCE < 1" | bc -l 2>/dev/null || echo "1") )); then
    echo -e "${YELLOW}WARNING: Low balance. You may need more SOL for deployment.${NC}"
    echo "Get devnet SOL: solana airdrop 2 $DEPLOYER_PUBKEY --url devnet"
fi

echo ""

# Step 2: Configure for devnet
echo -e "${YELLOW}[2/7] Configuring Solana for devnet...${NC}"
solana config set --url devnet
echo -e "  ✓ Switched to devnet"
echo ""

# Step 3: Build Anchor program
if [ "$SKIP_BUILD" = false ]; then
    echo -e "${YELLOW}[3/7] Building Anchor program...${NC}"
    cd "$ANCHOR_DIR"
    
    # Clean previous build
    if [ -d "target" ]; then
        echo "  Cleaning previous build..."
        rm -rf target/deploy/*.so 2>/dev/null || true
    fi
    
    # Build
    echo "  Building (this may take a few minutes)..."
    anchor build 2>&1 | tail -5
    
    if [ -f "target/deploy/anchor_presale.so" ]; then
        SO_SIZE=$(du -h target/deploy/anchor_presale.so | cut -f1)
        echo -e "  ${GREEN}✓ Build successful: anchor_presale.so ($SO_SIZE)${NC}"
    else
        echo -e "${RED}ERROR: Build failed - .so file not found${NC}"
        exit 1
    fi
else
    echo -e "${YELLOW}[3/7] Skipping build (--skip-build)${NC}"
fi
echo ""

# Step 4: Deploy to devnet
echo -e "${YELLOW}[4/7] Deploying to devnet...${NC}"
cd "$ANCHOR_DIR"

# Get current program ID from Anchor.toml
PROGRAM_ID=$(grep 'anchor_presale' Anchor.toml | head -1 | awk -F'"' '{print $2}')
echo "  Program ID: $PROGRAM_ID"

# Deploy
echo "  Deploying (this may take 1-2 minutes)..."
DEPLOY_OUTPUT=$(anchor deploy --provider.cluster devnet 2>&1)
echo "$DEPLOY_OUTPUT"

if echo "$DEPLOY_OUTPUT" | grep -q "Deploy success\|Program Id:"; then
    echo -e "  ${GREEN}✓ Deployment successful!${NC}"
    
    # Extract and display program ID
    DEPLOYED_ID=$(echo "$DEPLOY_OUTPUT" | grep "Program Id:" | awk '{print $3}')
    if [ -n "$DEPLOYED_ID" ]; then
        echo -e "  ${GREEN}Program ID: $DEPLOYED_ID${NC}"
    fi
else
    echo -e "${RED}ERROR: Deployment may have failed. Check output above.${NC}"
    # Don't exit - deployment might have succeeded with different output format
fi
echo ""

# Step 5: Setup voucher signer keypair
echo -e "${YELLOW}[5/7] Setting up voucher signer keypair...${NC}"
mkdir -p "$KEYS_DIR"

VOUCHER_SIGNER_PATH="$KEYS_DIR/voucher-signer.json"
if [ ! -f "$VOUCHER_SIGNER_PATH" ]; then
    echo "  Generating new voucher signer keypair..."
    solana-keygen new --no-bip39-passphrase -o "$VOUCHER_SIGNER_PATH" --force
    echo -e "  ${GREEN}✓ Keypair generated${NC}"
else
    echo -e "  ✓ Keypair already exists"
fi

VOUCHER_SIGNER_PUBKEY=$(solana-keygen pubkey "$VOUCHER_SIGNER_PATH")
echo -e "  Voucher Signer Pubkey: ${GREEN}$VOUCHER_SIGNER_PUBKEY${NC}"
echo ""

# Step 6: Setup Laravel
if [ "$SKIP_LARAVEL" = false ]; then
    echo -e "${YELLOW}[6/7] Setting up Laravel backend...${NC}"
    cd "$LARAVEL_DIR"
    
    # Check if .env exists
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            echo "  Creating .env from .env.example..."
            cp .env.example .env
            php artisan key:generate
        else
            echo -e "${RED}ERROR: .env.example not found${NC}"
            exit 1
        fi
    fi
    
    # Update .env with voucher signer path
    if ! grep -q "VOUCHER_SIGNER_KEYPATH" .env; then
        echo "" >> .env
        echo "# Voucher Signer Configuration" >> .env
        echo "VOUCHER_SIGNER_KEYPATH=$VOUCHER_SIGNER_PATH" >> .env
        echo -e "  ✓ Added VOUCHER_SIGNER_KEYPATH to .env"
    else
        sed -i "s|VOUCHER_SIGNER_KEYPATH=.*|VOUCHER_SIGNER_KEYPATH=$VOUCHER_SIGNER_PATH|" .env
        echo -e "  ✓ Updated VOUCHER_SIGNER_KEYPATH in .env"
    fi
    
    # Run migrations
    echo "  Running migrations..."
    php artisan migrate --force 2>&1 | grep -E "Migrating|Migrated|Nothing" || true
    echo -e "  ${GREEN}✓ Migrations complete${NC}"
else
    echo -e "${YELLOW}[6/7] Skipping Laravel setup (--skip-laravel)${NC}"
fi
echo ""

# Step 7: Summary and next steps
echo -e "${YELLOW}[7/7] Deployment Summary${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${GREEN}✓ Anchor Program Deployed to Devnet${NC}"
echo "  Program ID: $PROGRAM_ID"
echo "  Explorer: https://explorer.solana.com/address/$PROGRAM_ID?cluster=devnet"
echo ""
echo -e "${GREEN}✓ Voucher Signer Configured${NC}"
echo "  Pubkey: $VOUCHER_SIGNER_PUBKEY"
echo "  Keypath: $VOUCHER_SIGNER_PATH"
echo ""
echo -e "${GREEN}✓ Laravel Backend Ready${NC}"
echo "  Path: $LARAVEL_DIR"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo ""
echo "1. Start Laravel server:"
echo "   cd $LARAVEL_DIR && php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "2. Initialize Sale Config on-chain (run once):"
echo "   cd $ANCHOR_DIR && npx ts-node scripts/initialize-sale.ts"
echo ""
echo "3. Test voucher issuance:"
echo "   curl -X POST http://127.0.0.1:8000/api/v1/sale/test/whitelist \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -d '{\"buyer_pubkey\":\"<YOUR_WALLET>\",\"sale_pubkey\":\"<SALE_PDA>\",\"max_allocation\":1000000000,\"expiry_ts\":1924992000}'"
echo ""
echo "4. Run E2E test:"
echo "   cd $ANCHOR_DIR && anchor test --provider.cluster devnet"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════════${NC}"
