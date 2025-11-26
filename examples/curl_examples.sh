#!/bin/bash
# MyXenPay API Examples using cURL
# These examples demonstrate the main API endpoints

BASE_URL="http://localhost:8000/api"

# ============================================
# Health Check
# ============================================
echo "=== Health Check ==="
curl -s $BASE_URL/health | jq .

# ============================================
# Register a new user
# ============================================
echo -e "\n=== Register ==="
curl -s -X POST $BASE_URL/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }' | jq .

# ============================================
# Login and get token
# ============================================
echo -e "\n=== Login ==="
RESPONSE=$(curl -s -X POST $BASE_URL/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }')
echo $RESPONSE | jq .

# Extract token (requires jq)
TOKEN=$(echo $RESPONSE | jq -r '.token')
echo -e "\nToken: $TOKEN"

# ============================================
# Get Profile
# ============================================
echo -e "\n=== Get Profile ==="
curl -s -X GET $BASE_URL/auth/profile \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# ============================================
# List Wallets
# ============================================
echo -e "\n=== List Wallets ==="
curl -s -X GET $BASE_URL/wallet \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# ============================================
# Get Wallet Details
# ============================================
echo -e "\n=== Get Wallet Details ==="
curl -s -X GET $BASE_URL/wallet/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# ============================================
# Transfer Funds (Internal)
# ============================================
echo -e "\n=== Transfer Funds ==="
curl -s -X POST $BASE_URL/wallet/transfer \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "to_address": "RECIPIENT_WALLET_ADDRESS",
    "amount": 10.5,
    "description": "Test transfer"
  }' | jq .

# ============================================
# Withdraw to Blockchain
# ============================================
echo -e "\n=== Withdraw to Blockchain ==="
curl -s -X POST $BASE_URL/wallet/withdraw \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "to_address": "7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU",
    "amount": 10
  }' | jq .

# ============================================
# Transaction History
# ============================================
echo -e "\n=== Transaction History ==="
curl -s -X GET "$BASE_URL/wallet/1/transactions?page=1&per_page=20" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .

# ============================================
# Logout
# ============================================
echo -e "\n=== Logout ==="
curl -s -X POST $BASE_URL/auth/logout \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq .
