# University Module

## Purpose
Manages university/educational institution accounts, student verification, and campus payments.

## Expected Endpoints
- POST /api/university/register - Register university
- GET /api/university/students - List students
- POST /api/university/student/verify - Verify student enrollment
- GET /api/university/payments - Campus payment history

## Interfaces
- UniversityServiceInterface
- StudentVerificationInterface

## TODO
- [ ] Implement student ID verification
- [ ] Add campus card integration
- [ ] Create tuition payment system
