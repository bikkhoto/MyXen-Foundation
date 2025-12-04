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
# MyXen Super-App Ecosystem (V2)

A unified, enterprise-grade, KYC-secured crypto super-app built on **Laravel (Backend)**, **Solana (Blockchain)**, and **Flutter (Mobile)**.

This repository contains the full architecture, **all ecosystem services**, API standards, and development guidelines for MyXen Foundation V2.

This version includes **ALL departments**, including Governance DAO, Social, Women Empowerment, and Support System.

---

# 🚀 Overview

MyXen is a large-scale, real-world crypto super-app containing multiple interconnected departments:

* Payments (MyXenPay)
* Remittance
* Travel Bookings
* Merchant Ecosystem
* University Platform
* Store & Marketplace
* Freelancer Marketplace
* Payroll & Employee Management
* Charity (MyXen Life)
* Women Empowerment Program
* Meme Engine
* MyXenLocker (Token Locking & Vesting)
* Social Platform (MyXen.Social)
* Governance DAO
* Developer Portal & API
* Support Center (Helpdesk)
* Notification & Messaging System
* Analytics & Reporting
* Multisig & Treasury Control
* Admin Panel (Backoffice)

All services operate under unified authentication, KYC enforcement, and the $MYXN token ecosystem.

---

# 📂 Repository Structure

```
/myxen
  /services
    /auth-service
    /kyc-service
    /locker-service
    /treasury-service
    /payments-service
    /presale-service
    /merchant-service
    /university-service
    /freelancer-service
    /payroll-service
    /remittance-service
    /store-service
    /travel-service
    /social-service
    /meme-service
    /women-service
    /support-service
    /governance-service
    /multisig-service
    /solana-service
    /notification-service
    /analytics-service
    /admin-panel
    /developer-portal
  /mobile
  /web
  /infra
  /docs
  /scripts
  README.md
```

Every service contains its own OpenAPI contract, DB schema, and business logic.

---

# 🛠 Tech Stack

### Backend

* Laravel 10 LTS (API-first)
* PostgreSQL
* Redis + Horizon
* S3/MinIO

### Blockchain

* Solana SPL Token
* Node.js Solana integration service

### Mobile

* Flutter
* Deep-link wallet auth

### DevOps

* Docker / K8s
* GitHub Actions
* Terraform
* Prometheus / Grafana / Sentry

---

# 🔐 Security Principles

* Mandatory KYC
* Role-based access control (RBAC)
* Multisig for Treasury & Burn
* KMS for key protection
* Immutable audit logs
* AML monitoring
* Public monthly burn

---

# 🧱 Core Services (Full List)

Below is the **complete set of MyXen services**, each with core responsibilities:

### Auth Service

* Wallet challenge → signature → JWT
* Role mapping

### KYC Service

* Document uploads
* KYC provider API
* Manual review queue

### Locker Service (MyXen.Locker)

* Vesting
* LP Locks
* Team locks
* Multisig vault

### Treasury Service

* Platform fee accounting
* Burn execution system
* Liquidity routing

### Payments Service

* QR payments
* Tuition payments (0% fee)
* Settlement engine
* Cashback

### Remittance Service

* Crypto → fiat partners
* AML rules

### University Service

* Tuition billing
* Student portal
* University admin portal

### Merchant Service

* Merchant onboarding
* POS/QR management

### Freelancer Service

* Escrow jobs
* Low-fee payouts

### Payroll Service

* Salary distribution
* Employee task module

### Store / Marketplace

* Multi-vendor listings
* Checkout via $MYXN

### Travel Service

* Flight/hotel booking APIs
* Cashback

### Social Service (MyXen.Social)

* Posts, feeds, profiles
* Reward system
* Meme community integration

### Meme Engine

* New meme tokens
* Buyback & lock
* Holder rewards

### Women Empowerment Service

* Zero-interest micro-loans
* Grants
* Skill development
* Protected fund distribution

### Support Service

* Ticketing (User / Merchant / University / KYC)
* Live chat integration
* Incident management

### Governance Service (DAO)

* Proposal creation
* Voting system (KYC-only DAO)
* Weighted governance

### Multisig Service

* Transaction proposal creation
* Signer workflows
* Treasury & burn execution

### Notification Service

* Email, SMS, push, in-app
* Device token registry

### Analytics Service

* Event ingestion
* BI dashboards
* Data warehousing

### Admin Panel

* CEO dashboard
* KYC reviewers
* Fraud monitoring
* Global settings

### Developer Portal

* API docs
* SDKs
* Webhooks
* Sandbox

---

# 🔗 API Contract (OpenAPI)

All services implement their own OpenAPI specs under:

```
/services/*/openapi/*.json
```

The API Gateway merges all contracts.

---

# 🌐 Solana Integration Microservice

Handles all blockchain responsibilities:

* Signature verification
* Building transactions
* Broadcasting
* Confirmation callbacks

---

# 📱 Mobile Architecture

* Flutter super-app interface
* Wallet connect via deep-link
* Modular screens for each department
* Secure storage for JWT

Bottom nav:

```
Home | Pay | Travel | Store | Profile
```

---

# ⚙ CI/CD Pipeline

* Tests → OpenAPI validation → Security scan → Build → Deploy → Smoke test → Manual approval → Production

---

# 🧪 Testing Strategy

* Unit test
* Integration test
* Devnet E2E
* Smart contract audits
* Pen-tests

---

# 🔥 MYXN Token Layer

* Mint: CHXoAEvTi3FAEZMkWDJJmUSRXxYAoeco4bDMDZQJVWen
* Treasury: 6S4eDdYXABgtmuk3waLM63U2KHgExcD9mco7MuyG9f5G
* Burn Wallet: 13m6FRnMKjcyuX53ryBW7AJkXG4Dt9SR5y1qPjBxSQhc
* Pre-sale: 500M tokens
* Monthly burn queue

---

# 🗺 Future Roadmap

* MyXenCard
* Global merchant expansion
* Fully automated Governance DAO
* AI-driven fraud detection
* Education & skill platform for Women's Empowerment.
