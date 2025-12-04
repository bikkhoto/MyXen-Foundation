# Auth Service Module

Complete authentication service implementation for MyXen API using Laravel Sanctum.

## Overview

The Auth Service provides secure user authentication with token-based API access using Laravel Sanctum. It includes user registration, login, logout, and profile management with strong validation and PSR-12 compliant code.

## Structure

```
app/
├── Services/Auth/Controllers/
│   └── AuthController.php          # Main auth controller
├── Http/Requests/
│   ├── RegisterRequest.php         # Registration validation
│   └── LoginRequest.php            # Login validation
└── Models/
    └── User.php                    # User model with Sanctum traits

database/migrations/
└── 2014_10_12_000000_create_users_table.php  # Users table schema

routes/
└── api.php                         # API routes with v1/auth prefix

tests/Feature/
└── AuthTest.php                    # Comprehensive feature tests

docs/
└── AUTH_API.md                     # Complete API documentation
```

## Features

✅ User Registration with strong password validation  
✅ User Login with credential verification  
✅ Token-based authentication (Laravel Sanctum)  
✅ Logout with token revocation  
✅ Protected profile endpoint  
✅ Role-based user system (user, admin, merchant)  
✅ KYC status tracking  
✅ Email verification support  
✅ Comprehensive validation rules  
✅ PSR-12 code style  
✅ Full test coverage (7 tests, 41 assertions)

## Database Schema

### Users Table
- `id` - Primary key
- `name` - User's full name
- `email` - Unique email address
- `email_verified_at` - Email verification timestamp
- `password` - Bcrypt hashed password
- `role` - User role (user, admin, merchant) - default: 'user'
- `is_verified` - Account verification status - default: false
- `kyc_status` - KYC verification status - default: 'pending'
- `remember_token` - Remember me token
- `timestamps` - created_at, updated_at

## API Endpoints

### Public Routes
- `POST /api/v1/auth/register` - Register new user
- `POST /api/v1/auth/login` - Login user

### Protected Routes (require Bearer token)
- `POST /api/v1/auth/logout` - Logout current user
- `GET /api/v1/auth/me` - Get authenticated user profile

## Validation Rules

### Registration
```php
'name' => 'required|string|min:2|max:255'
'email' => 'required|email:rfc|unique:users|max:255'
'password' => 'required|confirmed|min:8' + uppercase + lowercase + numbers + symbols
'role' => 'optional|in:user,admin,merchant'
```

### Login
```php
'email' => 'required|email'
'password' => 'required|string'
```

## Testing

Run all auth tests:
```bash
php artisan test --filter AuthTest
```

Expected output:
```
PASS  Tests\Feature\AuthTest
✓ user can register with valid data
✓ user cannot register with duplicate email
✓ user can login with valid credentials
✓ user cannot login with invalid credentials
✓ authenticated user can logout
✓ authenticated user can get profile
✓ unauthenticated user cannot access protected routes

Tests: 7 passed (41 assertions)
```

## Usage Examples

### 1. Register a New User
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123!@#",
    "password_confirmation": "SecurePass123!@#"
  }'
```

Response (201):
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "user",
      "is_verified": false,
      "kyc_status": "pending",
      "created_at": "2025-12-03T14:00:00.000000Z"
    },
    "token": "1|abc123...",
    "token_type": "Bearer"
  }
}
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePass123!@#"
  }'
```

### 3. Get Profile (Protected)
```bash
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### 4. Logout (Protected)
```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## Security Features

1. **Password Hashing**: Automatic bcrypt hashing via mutator
2. **Token Authentication**: Laravel Sanctum bearer tokens
3. **Strong Password Policy**: Min 8 chars, mixed case, numbers, symbols
4. **Email Uniqueness**: Prevents duplicate accounts
5. **Token Revocation**: Logout revokes current token
6. **Hidden Attributes**: Password and remember_token hidden from JSON

## HTTP Response Codes

- `200 OK` - Successful request
- `201 Created` - User registered successfully
- `401 Unauthorized` - Invalid or missing token
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

## Next Steps

### Recommended Enhancements
1. Email verification workflow
2. Password reset functionality
3. Two-factor authentication (2FA)
4. Rate limiting on auth endpoints
5. Account lockout after failed attempts
6. Refresh token implementation
7. Social authentication (OAuth)
8. Audit logging for auth events

### Integration with Other Services
- **KYC Service**: Update `kyc_status` after verification
- **Admin Panel**: Role-based access control
- **Payments Service**: Link user accounts to payment methods

## Development

### Code Style
All code follows PSR-12 standards with:
- Proper type hints
- DocBlocks on all methods
- Consistent formatting
- Meaningful variable names

### Database Migrations
Refresh migrations:
```bash
php artisan migrate:fresh
```

### Run Development Server
```bash
cd services/api
php artisan serve
```

API available at: `http://127.0.0.1:8000`

## Documentation

Full API documentation available at: `docs/AUTH_API.md`

## License

Part of the MyXen Foundation monorepo.
