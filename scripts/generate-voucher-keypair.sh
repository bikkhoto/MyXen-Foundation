#!/bin/bash

################################################################################
# Voucher Signer Keypair Generation Script
#
# This script generates an ed25519 keypair for the Laravel voucher signing
# service. The keypair is used to cryptographically sign presale vouchers
# that are verified on-chain by the Solana Anchor program.
#
# SECURITY WARNING:
# - Keep this keypair secure and backed up
# - Never commit to version control
# - Use HSM or key management service in production
# - Rotate keys if compromised
################################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default keypair path
DEFAULT_KEYPATH="$HOME/.myxen/keys/voucher-signer.json"

echo -e "${BLUE}================================${NC}"
echo -e "${BLUE}Voucher Signer Keypair Generator${NC}"
echo -e "${BLUE}================================${NC}"
echo ""

# Check if Solana CLI is installed
if ! command -v solana-keygen &> /dev/null; then
    echo -e "${RED}Error: solana-keygen not found${NC}"
    echo -e "${YELLOW}Please install Solana CLI tools:${NC}"
    echo "  sh -c \"\$(curl -sSfL https://release.solana.com/stable/install)\""
    exit 1
fi

# Ask user for keypair path
echo -e "${YELLOW}Enter keypair path [default: $DEFAULT_KEYPATH]:${NC}"
read -r KEYPATH
KEYPATH=${KEYPATH:-$DEFAULT_KEYPATH}

# Check if keypair already exists
if [ -f "$KEYPATH" ]; then
    echo -e "${YELLOW}Warning: Keypair already exists at $KEYPATH${NC}"
    echo -e "${RED}Overwriting will invalidate all previously issued vouchers!${NC}"
    echo -e "${YELLOW}Do you want to continue? (yes/no):${NC}"
    read -r CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo -e "${GREEN}Aborted. Existing keypair preserved.${NC}"
        exit 0
    fi
    
    # Backup existing keypair
    BACKUP_PATH="${KEYPATH}.backup.$(date +%Y%m%d-%H%M%S)"
    echo -e "${BLUE}Backing up existing keypair to: $BACKUP_PATH${NC}"
    cp "$KEYPATH" "$BACKUP_PATH"
fi

# Create directory if it doesn't exist
KEYDIR=$(dirname "$KEYPATH")
mkdir -p "$KEYDIR"

echo ""
echo -e "${BLUE}Generating new ed25519 keypair...${NC}"

# Generate keypair using solana-keygen
solana-keygen new --outfile "$KEYPATH" --no-bip39-passphrase --force

# Secure the keypair (read/write for owner only)
chmod 600 "$KEYPATH"

echo ""
echo -e "${GREEN}✓ Keypair generated successfully!${NC}"
echo ""

# Display public key
PUBKEY=$(solana-keygen pubkey "$KEYPATH")
echo -e "${BLUE}Public Key (Base58):${NC} ${GREEN}$PUBKEY${NC}"
echo ""

# Instructions for Laravel configuration
echo -e "${YELLOW}==== Next Steps ====${NC}"
echo ""
echo "1. Add to Laravel .env file:"
echo -e "   ${GREEN}VOUCHER_SIGNER_KEYPATH=$KEYPATH${NC}"
echo ""
echo "2. Restart Laravel server:"
echo -e "   ${GREEN}php artisan config:clear${NC}"
echo -e "   ${GREEN}php artisan serve${NC}"
echo ""
echo "3. Update Anchor program with voucher signer public key"
echo "   (if using hardcoded verification)"
echo ""
echo -e "${YELLOW}==== Security Checklist ====${NC}"
echo ""
echo "☐ Backup keypair to secure offline storage"
echo "☐ Do NOT commit keypair to version control"
echo "☐ Restrict file permissions (chmod 600)"
echo "☐ Use environment variable for path"
echo "☐ Rotate keys periodically"
echo "☐ Monitor voucher issuance logs"
echo ""
echo -e "${RED}==== IMPORTANT ====${NC}"
echo -e "${RED}If this keypair is lost or compromised:${NC}"
echo -e "${RED}- All issued vouchers become invalid${NC}"
echo -e "${RED}- Buyers cannot complete purchases${NC}"
echo -e "${RED}- Generate new keypair and re-issue vouchers${NC}"
echo ""
echo -e "${GREEN}Keypair location: $KEYPATH${NC}"
echo -e "${GREEN}Public key: $PUBKEY${NC}"
echo ""
