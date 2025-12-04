# Contributing to MyXenPay

Thank you for your interest in contributing to MyXenPay! This document provides guidelines and information about contributing.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Commit Messages](#commit-messages)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)

## Code of Conduct

This project adheres to a Code of Conduct. By participating, you are expected to uphold this code. Please read [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## Getting Started

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/MyXen-Foundation-V2.git
   ```
3. Add the upstream remote:
   ```bash
   git remote add upstream https://github.com/bikkhoto/MyXen-Foundation-V2.git
   ```
4. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## Development Workflow

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan test
```

### Mobile (Flutter)

```bash
cd mobile
flutter pub get
flutter analyze
flutter test
```

## Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/) specification:

### Format

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### Types

- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code changes that neither fix bugs nor add features
- `perf`: Performance improvements
- `test`: Adding or correcting tests
- `chore`: Changes to build process or auxiliary tools

### Examples

```
feat(wallet): add transfer confirmation modal
fix(auth): correct token refresh logic
docs(api): update wallet endpoint documentation
test(wallet): add unit tests for balance calculation
```

## Pull Request Process

1. **Create a branch** from `develop` (not `main`)
2. **Make your changes** following our coding standards
3. **Write tests** for new functionality
4. **Update documentation** if needed
5. **Run tests** and ensure they pass:
   ```bash
   # Backend
   cd backend && php artisan test
   
   # Mobile
   cd mobile && flutter test
   ```
6. **Create a Pull Request** targeting the `develop` branch
7. **Fill out the PR template** completely
8. **Request review** from maintainers

### PR Checklist

- [ ] Code follows project style guidelines
- [ ] Tests added/updated and passing
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] PR description explains changes

## Coding Standards

### PHP (Backend)

We follow PSR-12 coding standard. Use Laravel Pint for formatting:

```bash
cd backend
./vendor/bin/pint
```

#### Key Guidelines

- Use type declarations for parameters and return types
- Use strict types: `declare(strict_types=1);`
- Document complex methods with PHPDoc
- Keep methods focused and small
- Use dependency injection

### Dart (Mobile)

Follow the [Dart Style Guide](https://dart.dev/guides/language/effective-dart/style).

```bash
cd mobile
flutter analyze
```

#### Key Guidelines

- Use `const` constructors when possible
- Prefer `final` for variables that don't change
- Use named parameters for clarity
- Handle errors appropriately
- Write descriptive widget names

### General Guidelines

- Write meaningful variable and function names
- Keep functions/methods focused on a single task
- Comment complex logic, not obvious code
- Add TODO comments for temporary solutions

## Testing

### Backend Tests

```bash
cd backend
php artisan test                    # Run all tests
php artisan test --filter=Auth      # Run specific tests
php artisan test --coverage         # With coverage report
```

### Mobile Tests

```bash
cd mobile
flutter test                        # Run all tests
flutter test test/services/         # Run specific directory
flutter test --coverage             # With coverage report
```

## Questions?

If you have questions, feel free to:

- Open a GitHub Issue
- Start a Discussion
- Reach out to maintainers

Thank you for contributing! 🎉
