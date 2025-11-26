# Wallet Module

## Purpose
Manages user wallets, balances, and internal transfers within the MyXenPay ecosystem.

## Expected Endpoints
- GET /api/wallet - List user wallets
- GET /api/wallet/{id} - Get wallet details
- POST /api/wallet/transfer - Internal transfer
- POST /api/wallet/withdraw - Blockchain withdrawal
- GET /api/wallet/{id}/transactions - Transaction history

## Interfaces
- WalletServiceInterface
- TransactionServiceInterface

## TODO
- [ ] Implement multi-currency support
- [ ] Add transaction fee calculation
- [ ] Create balance reconciliation service
