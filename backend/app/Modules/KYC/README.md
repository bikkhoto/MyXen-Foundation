# KYC Module

## Purpose
Know Your Customer verification and identity management.

## Expected Endpoints
- POST /api/kyc/submit - Submit KYC documents
- GET /api/kyc/status - KYC verification status
- POST /api/kyc/verify - Admin verify KYC
- GET /api/kyc/documents - List submitted documents

## Interfaces
- KYCServiceInterface
- DocumentVerificationInterface
- IdentityProviderInterface

## TODO
- [ ] Integrate third-party KYC provider
- [ ] Add document OCR processing
- [ ] Implement AML screening
