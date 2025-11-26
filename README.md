# MyXen Foundation V2 — MyXenPay Ecosystem

A production-ready monorepo containing the MyXenPay ecosystem: Laravel backend API and Flutter mobile application.

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MyXenPay Architecture                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────┐     ┌─────────────┐     ┌─────────────────────────┐      │
│   │   Mobile    │────▶│   API       │────▶│    Solana Blockchain    │      │
│   │   (Flutter) │◀────│   (Laravel) │◀────│    (MYXN Token)         │      │
│   └─────────────┘     └──────┬──────┘     └─────────────────────────┘      │
│                              │                                              │
│                       ┌──────┴──────┐                                       │
│                       │             │                                       │
│                    ┌──▼──┐     ┌────▼───┐                                   │
│                    │MySQL│     │ Redis  │                                   │
│                    └─────┘     └────────┘                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## 🚀 Quick Start

### Prerequisites

- Docker & Docker Compose
- PHP 8.3+ (for local development)
- Flutter 3.0+ (for mobile development)
- Node.js 18+ (optional, for asset compilation)

### One-Click Development Setup

```bash
# Clone the repository
git clone https://github.com/bikkhoto/MyXen-Foundation-V2.git
cd MyXen-Foundation-V2

# Start all services
docker-compose up --build

# In another terminal, run migrations
docker-compose exec workspace php artisan migrate --seed
```

The API will be available at `http://localhost:8000`

### Manual Setup (Backend)

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Mobile Setup

```bash
cd mobile
cp .env.example .env
flutter pub get
flutter run
```

## 📁 Repository Structure

```
MyXen-Foundation-V2/
├── backend/                 # Laravel 11/12 API
│   ├── app/
│   │   ├── Http/Controllers/Api/  # API Controllers
│   │   ├── Models/                # Eloquent Models
│   │   ├── Modules/               # Modular architecture
│   │   ├── Services/Blockchain/   # Solana integration
│   │   └── Jobs/                  # Queue jobs
│   ├── config/
│   ├── database/
│   ├── routes/api.php
│   └── tests/
├── mobile/                  # Flutter Mobile App
│   ├── lib/
│   │   ├── features/        # Screen modules
│   │   ├── services/        # API & business logic
│   │   └── themes/          # UI theming
│   └── test/
├── docs/                    # Documentation
│   └── openapi.yaml         # API specification
├── infra/                   # Infrastructure
│   └── docker/              # Docker configurations
├── modules/                 # External module placeholders
├── examples/                # Integration examples
├── docker-compose.yml       # Docker orchestration
└── .github/workflows/       # CI/CD pipelines
```

## 🔧 Backend Modules

The backend uses a modular architecture with 20 pre-scaffolded modules:

| Module | Description |
|--------|-------------|
| Core | Foundational services and utilities |
| Wallet | Balance management and transfers |
| Merchant | QR payments and merchant features |
| University | Campus payments and student verification |
| Locker | Smart locker integration |
| KYC | Identity verification |
| Notification | Multi-channel notifications |
| Billing | Subscriptions and invoicing |
| Reporting | Analytics and reports |
| Identity | Digital identity / SSO |
| Exchange | Token swaps |
| AdminPanel | Admin dashboard |
| Payments | Payment processing |
| Compliance | AML/CFT monitoring |
| Analytics | Business intelligence |
| Support | Customer support |
| Referral | Referral program |
| Rewards | Loyalty points |
| Gateway | External integrations |
| Audit | Activity logging |

## 🔐 API Authentication

The API uses Laravel Sanctum for token-based authentication:

```bash
# Register
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John","email":"john@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Authenticated request
curl -X GET http://localhost:8000/api/wallet \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 📖 API Documentation

Swagger documentation is available at `/api/documentation` when running the backend.

Full OpenAPI spec: [docs/openapi.yaml](docs/openapi.yaml)

## 🧪 Testing

### Backend Tests

```bash
cd backend
php artisan test
```

### Mobile Tests

```bash
cd mobile
flutter test
```

## 🔄 CI/CD

GitHub Actions workflows are configured for:

- **Backend CI**: PHP linting, unit tests
- **Mobile CI**: Flutter analyze, tests, APK build

## 📝 Contributing

Please read [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines.

## 🔒 Security

For security issues, please email security@myxenpay.com instead of using public issues.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## Next Steps

- [ ] Implement Solana signing with secure key management (HSM/KMS)
- [ ] Create Merchant QR payment module
- [ ] Integrate third-party KYC provider
- [ ] Add push notifications (Firebase)
- [ ] Implement mobile QR scanner
- [ ] Add biometric authentication
- [ ] Configure CI secret rotation
- [ ] Deploy to staging environment
