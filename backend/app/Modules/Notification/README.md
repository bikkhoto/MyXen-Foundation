# Notification Module

## Purpose
Multi-channel notification system (push, email, SMS, in-app).

## Expected Endpoints
- GET /api/notifications - List notifications
- POST /api/notifications/read - Mark as read
- PUT /api/notifications/settings - Update preferences
- POST /api/notifications/subscribe - Subscribe to topics

## Interfaces
- NotificationServiceInterface
- PushNotificationInterface
- EmailServiceInterface
- SMSServiceInterface

## TODO
- [ ] Implement Firebase push notifications
- [ ] Add email templates
- [ ] Create notification batching
