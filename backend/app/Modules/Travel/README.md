# Travel Module

## Purpose
Travel booking services - flights, hotels, car rentals payable with MYXN.

## Expected Endpoints
- GET /api/travel/flights/search - Search flights
- GET /api/travel/hotels/search - Search hotels
- POST /api/travel/bookings - Create booking
- GET /api/travel/bookings - List bookings
- POST /api/travel/bookings/{id}/cancel - Cancel booking

## Interfaces
- TravelServiceInterface
- FlightProviderInterface
- HotelProviderInterface
- BookingManagerInterface

## TODO
- [ ] Integrate flight aggregator API
- [ ] Add hotel booking system
- [ ] Implement car rental integration
