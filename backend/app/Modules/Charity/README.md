# Charity (MyXen Life) Module

## Purpose
Charitable giving platform with transparent donation tracking on blockchain.

## Expected Endpoints
- GET /api/charity/campaigns - List campaigns
- POST /api/charity/donate - Make donation
- GET /api/charity/donations - Donation history
- GET /api/charity/impact - Impact metrics
- POST /api/charity/campaigns - Create campaign (NGOs)

## Interfaces
- CharityServiceInterface
- DonationTrackerInterface
- ImpactReportingInterface
- NGOVerificationInterface

## TODO
- [ ] Implement donation tracking
- [ ] Add campaign verification
- [ ] Create impact reporting dashboard
