#!/bin/bash
# MyXenPay API Examples using HTTPie
# Install HTTPie: pip install httpie

BASE_URL="http://localhost:8000/api"
TOKEN=""

# ============================================
# Health Check
# ============================================
echo "=== Health Check ==="
http GET $BASE_URL/health

# ============================================
# Authentication
# ============================================

# Register a new user
echo "\n=== Register ==="
http POST $BASE_URL/auth/register \
  name="John Doe" \
  email="john@example.com" \
  password="password123" \
  password_confirmation="password123"

# Login
echo "\n=== Login ==="
RESPONSE=$(http POST $BASE_URL/auth/login \
  email="john@example.com" \
  password="password123")
echo $RESPONSE

# Extract token (requires jq)
TOKEN=$(echo $RESPONSE | jq -r '.token')
echo "Token: $TOKEN"

# Get Profile
echo "\n=== Get Profile ==="
http GET $BASE_URL/auth/profile \
  "Authorization:Bearer $TOKEN"

# ============================================
# Wallet Operations
# ============================================

# List Wallets
echo "\n=== List Wallets ==="
http GET $BASE_URL/wallet \
  "Authorization:Bearer $TOKEN"

# Get Wallet Details (replace 1 with actual wallet ID)
echo "\n=== Get Wallet Details ==="
http GET $BASE_URL/wallet/1 \
  "Authorization:Bearer $TOKEN"

# Transfer (internal)
echo "\n=== Transfer ==="
http POST $BASE_URL/wallet/transfer \
  "Authorization:Bearer $TOKEN" \
  to_address="RECIPIENT_ADDRESS" \
  amount:=10.5 \
  description="Test transfer"

# Withdraw (to blockchain)
echo "\n=== Withdraw ==="
http POST $BASE_URL/wallet/withdraw \
  "Authorization:Bearer $TOKEN" \
  to_address="7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU" \
  amount:=10

# Transaction History
echo "\n=== Transaction History ==="
http GET "$BASE_URL/wallet/1/transactions?page=1&per_page=20" \
  "Authorization:Bearer $TOKEN"

# ============================================
# Cleanup - Logout
# ============================================
echo "\n=== Logout ==="
http POST $BASE_URL/auth/logout \
  "Authorization:Bearer $TOKEN"
