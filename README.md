# MyXen Monorepo

This repository hosts the MyXen platform as a monorepo. It includes backend services, web and mobile clients, infrastructure-as-code, documentation, and developer tooling.

## Initial Developer Workflow

1. Ensure you have Git, Node.js, PHP, Composer, and your preferred package managers installed.
2. Bootstrap the repo structure:
   ```bash
   chmod +x ./bootstrap.sh
   ./bootstrap.sh
   ```
3. Create the base Laravel API (example):
   ```bash
   composer create-project laravel/laravel services/api
   ```
4. Install dependencies and configure environment variables in `.env` as needed.
5. Start developing within the appropriate service or client folder.

## Structure

```
services/
  auth-service/
  kyc-service/
  payments-service/
  admin-panel/
mobile/
web/
infra/
docs/
devops/
scripts/
```
