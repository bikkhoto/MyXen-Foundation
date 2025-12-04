# Compliance Module

## Purpose
Regulatory compliance, AML/CFT monitoring, and transaction screening.

## Expected Endpoints
- GET /api/compliance/status - Compliance status
- POST /api/compliance/report - File compliance report
- GET /api/compliance/alerts - Compliance alerts
- POST /api/compliance/review - Review flagged transaction

## Interfaces
- ComplianceServiceInterface
- AMLScreeningInterface
- SanctionsCheckInterface

## TODO
- [ ] Integrate sanctions screening API
- [ ] Add transaction monitoring rules
- [ ] Implement SAR generation
