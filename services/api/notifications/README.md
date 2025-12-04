# MyXen Foundation - Notification Service

A comprehensive multi-channel notification service for the MyXen Foundation Laravel API. Supports email, SMS (Twilio), Telegram, and push notifications with template-based message rendering, queue-based delivery, and automatic retry logic.

---

## Features

- **Multi-Channel Support**: Email, SMS, Telegram, and Push (extensible)
- **Template System**: Dynamic templates with variable substitution (`{{ variable }}` syntax)
- **Queue-Based Processing**: Asynchronous notification delivery via Laravel queues
- **Retry Logic**: Automatic retry with exponential backoff
- **Event-Driven**: Notifications triggered via events for loose coupling
- **Admin Management**: Full CRUD for templates and notification history
- **API Key Protection**: Secure internal endpoint for event creation
- **Comprehensive Testing**: Feature tests for all major functionality

---

## Architecture

### Models

- **Notification**: Stores notification records with status tracking (pending, queued, sent, failed)
- **NotificationTemplate**: Stores reusable templates for different event types and channels

### Controllers

- **NotificationController**: Admin endpoints for listing, viewing, and resending notifications; public endpoint for creating notification events
- **TemplateController**: Admin CRUD operations for managing templates

### Jobs

- **SendNotificationJob**: Processes notifications with retry logic and multi-channel support

### Services

- **SmsService**: Twilio integration for SMS delivery
- **TelegramService**: Telegram Bot API integration
- **GenericNotificationMail**: Mailable for email notifications

### Events & Listeners

- **NotificationCreated**: Event fired when a notification is created
- **NotificationListener**: Dispatches `SendNotificationJob` when `NotificationCreated` event is fired

### Middleware

- **EnsureApiKey**: Validates `X-API-KEY` header for internal endpoints

---

## Installation

### 1. Run Migrations

```bash
cd services/api
php artisan migrate
```

This creates:
- `notifications` table
- `notification_templates` table

### 2. Configure Environment Variables

Add the following to your `.env` file:

```dotenv
# Notification Service
NOTIFICATION_MAX_ATTEMPTS=3
NOTIFICATION_API_KEY=your-secure-random-api-key

# Twilio SMS
TWILIO_SID=your-twilio-account-sid
TWILIO_TOKEN=your-twilio-auth-token
TWILIO_FROM=+1234567890

# Telegram Bot
TELEGRAM_BOT_TOKEN=your-telegram-bot-token
```

**Generate a secure API key:**

```bash
php artisan tinker
>>> Str::random(32)
```

### 3. Configure Queue Worker

The notification service uses Laravel queues. Ensure you have a queue worker running:

```bash
php artisan queue:work --tries=3 --backoff=2,4,8
```

For production, use Supervisor to keep the queue worker running:

```ini
[program:myxen-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/services/api/artisan queue:work --sleep=3 --tries=3 --backoff=2,4,8
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
```

---

## Configuration

### Queue Settings

Retry behavior is configured via:

- `config/notifications.php`: `max_attempts` (default: 3)
- Exponential backoff: 2^attempt seconds (2s, 4s, 8s)

### SMS Provider (Twilio)

1. Sign up at [twilio.com](https://www.twilio.com/)
2. Get your Account SID and Auth Token from the dashboard
3. Purchase a phone number or use a trial number
4. Add credentials to `.env`

### Telegram Bot

1. Create a bot via [@BotFather](https://t.me/botfather)
2. Get the bot token from BotFather
3. Add `TELEGRAM_BOT_TOKEN` to `.env`
4. Use chat IDs for recipients (get via bot interactions or `getUpdates` API)

### Email

Configure Laravel's mail settings in `.env`:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@myxen.com"
MAIL_FROM_NAME="MyXen Foundation"
```

---

## Usage

### Creating Templates

Templates define the message structure for notification events. Create them via the admin API:

```bash
POST /api/v1/admin/templates
Authorization: Bearer {admin_token}
Content-Type: application/json

{
  "name": "User Registration Welcome",
  "event_type": "user.registered",
  "channel": "email",
  "subject_template": "Welcome to MyXen, {{ name }}!",
  "body_template": "Hello {{ name }},\n\nThank you for registering with {{ app_name }}.\n\nYour account is now active!",
  "is_active": true
}
```

**Supported Channels**: `email`, `sms`, `telegram`, `push`

**Variable Syntax**: Use `{{ variable_name }}` in templates. Variables are replaced from the `payload` when creating notifications.

### Triggering Notifications

Notifications are created via the internal API endpoint (protected by API key):

```bash
POST /api/v1/notifications/events
X-API-KEY: your-secure-api-key
Content-Type: application/json

{
  "user_id": 123,
  "event_type": "user.registered",
  "channel": "email",
  "payload": {
    "name": "John Doe",
    "app_name": "MyXen Foundation"
  }
}
```

**Response:**

```json
{
  "success": true,
  "message": "Notification created and queued for sending.",
  "data": {
    "notification_id": 456,
    "event_type": "user.registered",
    "channel": "email",
    "status": "pending"
  }
}
```

The notification is:
1. Created with `status = pending`
2. Template is rendered with `payload` variables
3. `NotificationCreated` event is fired
4. `NotificationListener` dispatches `SendNotificationJob`
5. Job sends the notification and updates status to `sent` or `failed`

### Admin Management

**List Notifications** (with filters):

```bash
GET /api/v1/admin/notifications?event_type=user.registered&status=sent&channel=email
Authorization: Bearer {admin_token}
```

**View Notification**:

```bash
GET /api/v1/admin/notifications/{id}
Authorization: Bearer {admin_token}
```

**Resend Notification**:

```bash
POST /api/v1/admin/notifications/{id}/resend
Authorization: Bearer {admin_token}
```

---

## Testing

Run the notification service tests:

```bash
cd services/api
php artisan test --filter NotificationTest
```

**Test Coverage:**

- API key authentication
- Event creation and job dispatching
- Email sending via SendNotificationJob
- Resend functionality with attempt increment
- Admin listing with filters
- Template CRUD operations
- Template syntax validation

---

## API Reference

### Public/Internal Endpoints

| Method | Endpoint                     | Description                     | Auth         |
|--------|------------------------------|---------------------------------|--------------|
| POST   | `/api/v1/notifications/events` | Create notification event       | API Key      |

### Admin Endpoints

| Method | Endpoint                              | Description                     | Auth         |
|--------|---------------------------------------|---------------------------------|--------------|
| GET    | `/api/v1/admin/notifications`          | List notifications (paginated)  | Admin Token  |
| GET    | `/api/v1/admin/notifications/{id}`     | View notification details       | Admin Token  |
| POST   | `/api/v1/admin/notifications/{id}/resend` | Resend notification          | Admin Token  |
| GET    | `/api/v1/admin/templates`              | List templates                  | Admin Token  |
| GET    | `/api/v1/admin/templates/{id}`         | View template                   | Admin Token  |
| POST   | `/api/v1/admin/templates`              | Create template                 | Admin Token  |
| PUT    | `/api/v1/admin/templates/{id}`         | Update template                 | Admin Token  |
| DELETE | `/api/v1/admin/templates/{id}`         | Delete template                 | Admin Token  |

---

## Troubleshooting

### Notifications Not Sending

1. **Check queue worker**: `php artisan queue:work` must be running
2. **Check logs**: `storage/logs/laravel.log` for errors
3. **Verify credentials**: Ensure Twilio/Telegram credentials are correct
4. **Check notification status**: Use admin API to view notification status and attempts

### Failed Notifications

- Notifications with `status = failed` have exceeded `max_attempts`
- Check `attempts` column to see retry count
- Use resend endpoint to manually retry

### Template Variables Not Rendering

- Ensure `payload` contains all variables used in the template
- Variables are case-sensitive
- Syntax: `{{ variable }}` or `{{variable}}` (spaces optional)

### API Key Errors

- Ensure `NOTIFICATION_API_KEY` is set in `.env`
- Pass key in `X-API-KEY` header (not `Authorization`)
- Generate secure key with `Str::random(32)`

---

## License

Part of the MyXen Foundation project. All rights reserved.
