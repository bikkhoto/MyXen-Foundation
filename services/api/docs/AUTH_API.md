# Auth API Documentation

Base URL: `http://localhost:8000/api/v1/auth`

## Endpoints

### 1. Register User

**POST** `/register`

Creates a new user account and returns an authentication token.

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john.doe@example.com",
  "password": "SecurePass123!@#",
  "password_confirmation": "SecurePass123!@#",
  "role": "user"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "user",
      "is_verified": false,
      "kyc_status": "pending",
      "created_at": "2025-12-03T14:00:00.000000Z"
    },
    "token": "1|abc123def456...",
    "token_type": "Bearer"
  }
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

---

### 2. Login User

**POST** `/login`

Authenticates a user and returns an authentication token.

**Request Body:**
```json
{
  "email": "john.doe@example.com",
  "password": "SecurePass123!@#"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "user",
      "is_verified": false,
      "kyc_status": "pending"
    },
    "token": "2|xyz789abc123...",
    "token_type": "Bearer"
  }
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": [
      "The provided credentials are incorrect."
    ]
  }
}
```

---

### 3. Logout User (Protected)

**POST** `/logout`

Revokes the current authentication token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logout successful"
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

### 4. Get User Profile (Protected)

**GET** `/me`

Returns the authenticated user's profile information.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john.doe@example.com",
      "role": "user",
      "is_verified": false,
      "kyc_status": "pending",
      "created_at": "2025-12-03T14:00:00.000000Z",
      "updated_at": "2025-12-03T14:00:00.000000Z"
    }
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "message": "Unauthenticated."
}
```

---

## Testing Examples

### Using cURL

**Register:**
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john.doe@example.com",
    "password": "SecurePass123!@#",
    "password_confirmation": "SecurePass123!@#"
  }'
```

**Login:**
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john.doe@example.com",
    "password": "SecurePass123!@#"
  }'
```

**Get Profile:**
```bash
curl -X GET http://localhost:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Logout:**
```bash
curl -X POST http://localhost:8000/api/v1/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

---

## Validation Rules

### Register
- `name`: required, string, 2-255 characters
- `email`: required, valid email format, unique, max 255 characters
- `password`: required, min 8 characters, must contain uppercase, lowercase, numbers, and symbols
- `password_confirmation`: required, must match password
- `role`: optional, must be one of: user, admin, merchant

### Login
- `email`: required, valid email format
- `password`: required, string

---

## HTTP Status Codes

- `200 OK`: Request successful
- `201 Created`: Resource created successfully
- `401 Unauthorized`: Invalid or missing authentication token
- `422 Unprocessable Entity`: Validation error
- `500 Internal Server Error`: Server error
