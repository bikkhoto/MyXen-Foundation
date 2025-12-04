# Merchant Module

## Purpose
Provides merchant onboarding, QR payment processing, and merchant-specific features.

## Expected Endpoints
- POST /api/merchant/register - Register as merchant
- GET /api/merchant/profile - Merchant profile
- POST /api/merchant/payment/create - Create payment request
- GET /api/merchant/payment/{id} - Get payment status
- POST /api/merchant/qr/generate - Generate payment QR

## Interfaces
- MerchantServiceInterface
- PaymentServiceInterface
- QRGeneratorInterface

## TODO
- [ ] Implement merchant KYB workflow
- [ ] Add payment notifications
- [ ] Create settlement service
