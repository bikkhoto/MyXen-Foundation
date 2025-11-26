# MyXen Foundation V2

MyXen Foundation V2 — Full Ecosystem (Laravel 11 Backend API + Flutter Mobile App)

## 🚀 Overview

MyXenPay is a comprehensive digital payment ecosystem built with:
- **Backend**: Laravel 11 RESTful API with modular architecture
- **Mobile**: Flutter app with Riverpod state management
- **Blockchain**: Solana RPC integration for $MYXN token handling

## 📁 Project Structure

```
├── backend/                    # Laravel 11 API
│   ├── app/
│   │   ├── Http/Controllers/Api/   # API Controllers
│   │   ├── Models/                 # Eloquent Models
│   │   ├── Services/               # Business Logic Services
│   │   └── Http/Middleware/        # Custom Middleware
│   ├── config/                     # Configuration files
│   ├── database/migrations/        # Database migrations
│   ├── routes/api.php              # API Routes
│   └── tests/                      # PHPUnit Tests
│
└── flutter_app/                # Flutter Mobile App
    ├── lib/
    │   ├── core/                   # Core utilities & services
    │   ├── features/               # Feature modules
    │   │   ├── auth/               # Authentication
    │   │   ├── dashboard/          # Home dashboard
    │   │   ├── wallet/             # Wallet management
    │   │   ├── payments/           # QR payments
    │   │   └── profile/            # User profile
    │   └── shared/                 # Shared widgets & providers
    └── test/                       # Flutter tests
```

## ✨ Features

### Backend API
- 🔐 **Authentication** - Laravel Sanctum token-based auth
- 💰 **Wallet Management** - SOL & MYXN balance tracking
- 💳 **Merchant QR Payments** - Generate and scan QR codes for payments
- 🔗 **Solana RPC Integration** - Real blockchain connectivity
- 📋 **KYC Verification** - Multi-level identity verification
- 🎓 **University ID System** - Student verification and benefits
- 🔒 **Vault/Locker** - Secure asset storage with interest
- 🔔 **Notifications** - Real-time notification system
- 👨‍💼 **Admin Panel API** - Complete administration endpoints
- 📚 **Swagger Documentation** - OpenAPI 3.0 documentation

### Mobile App
- 📱 **Modern UI** - Material Design 3 with dark mode support
- 🔄 **State Management** - Flutter Riverpod
- 🛡️ **Secure Storage** - Encrypted token storage
- 📸 **QR Scanner** - Scan merchant QR codes
- 📊 **Dashboard** - Balance overview and quick actions
- 👤 **Profile Management** - User settings and KYC status

## 🛠️ Setup Instructions

### Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed demo data
php artisan db:seed

# Start development server
php artisan serve
```

### Flutter App Setup

```bash
cd flutter_app

# Install dependencies
flutter pub get

# Run the app
flutter run
```

## 📚 API Documentation

The API documentation is available via Swagger UI at `/api/documentation` when the server is running.

### Key API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Register new user |
| POST | `/api/auth/login` | User login |
| GET | `/api/wallet` | Get user wallet |
| POST | `/api/wallet/transfer` | Transfer funds |
| POST | `/api/merchants/register` | Register as merchant |
| POST | `/api/merchants/pay/{qr}` | Pay merchant via QR |
| GET | `/api/kyc/status` | Get KYC status |
| POST | `/api/kyc/documents` | Submit KYC document |
| GET | `/api/vault` | Get vault details |
| POST | `/api/vault/lock` | Lock vault |
| GET | `/api/notifications` | Get notifications |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/dashboard` | Admin dashboard stats |
| GET | `/api/admin/users` | List all users |
| PUT | `/api/admin/users/{id}` | Update user |
| GET | `/api/admin/kyc/pending` | Pending KYC documents |
| POST | `/api/admin/kyc/{id}/approve` | Approve KYC |

## 🔧 Configuration

### Solana Configuration

Edit `backend/config/solana.php`:

```php
'rpc_url' => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),
'myxn_token_mint' => env('MYXN_TOKEN_MINT', ''),
```

### KYC Levels

Edit `backend/config/kyc.php` to customize verification levels and limits.

## 🧪 Testing

### Backend Tests

```bash
cd backend
php artisan test
```

### Flutter Tests

```bash
cd flutter_app
flutter test
```

## 📝 Demo Accounts

After seeding the database:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@myxenpay.com | password |
| Merchant | merchant@myxenpay.com | password |
| User | user@myxenpay.com | password |

## 🏗️ Architecture

### Backend (Laravel 11)
- **Controllers**: Thin controllers with API responses
- **Services**: Business logic encapsulation
- **Models**: Eloquent ORM with relationships
- **Middleware**: Authentication and authorization
- **Config**: Modular configuration files

### Mobile (Flutter)
- **Riverpod**: State management
- **GoRouter**: Navigation
- **Dio**: HTTP client
- **Secure Storage**: Token management

## 📄 License

MIT License - See [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
