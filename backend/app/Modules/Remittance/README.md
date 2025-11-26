# Remittance Module

## Purpose
Cross-border money transfer and remittance services powered by MYXN token.

## Expected Endpoints
- POST /api/remittance/quote - Get transfer quote
- POST /api/remittance/send - Initiate transfer
- GET /api/remittance/history - Transfer history
- GET /api/remittance/recipients - Saved recipients
- GET /api/remittance/corridors - Available corridors

## Interfaces
- RemittanceServiceInterface
- ExchangeRateProviderInterface
- ComplianceCheckInterface

## TODO
- [ ] Integrate FX rate providers
- [ ] Add corridor-specific compliance
- [ ] Implement payout partner APIs
