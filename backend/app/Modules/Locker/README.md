# Locker Module

## Purpose
Smart locker integration for secure pickup/delivery using MyXenPay.

## Expected Endpoints
- GET /api/locker/locations - List locker locations
- POST /api/locker/reserve - Reserve a locker
- POST /api/locker/unlock - Unlock locker
- GET /api/locker/{id}/status - Locker status

## Interfaces
- LockerServiceInterface
- LockerHardwareInterface

## TODO
- [ ] Implement IoT locker communication
- [ ] Add reservation timeout handling
- [ ] Create locker availability tracking
