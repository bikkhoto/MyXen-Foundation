# MyXenPay Mobile App

Flutter mobile application for the MyXenPay ecosystem.

## Prerequisites

- Flutter SDK 3.0+
- Dart 3.0+
- Android Studio / Xcode
- VS Code (recommended)

## Getting Started

### 1. Clone and Setup

```bash
cd mobile
cp .env.example .env
flutter pub get
```

### 2. Environment Configuration

Edit `.env` with your API endpoint:

```
API_BASE_URL=http://localhost:8000/api
SOLANA_NETWORK=devnet
```

### 3. Run the App

```bash
# Run in debug mode
flutter run

# Run on specific device
flutter run -d <device_id>

# Run in release mode
flutter run --release
```

## Project Structure

```
lib/
├── main.dart                 # App entry point
├── src/
│   ├── app.dart             # App widget
│   └── router.dart          # Navigation routes
├── features/                 # Feature modules
│   ├── splash/
│   ├── onboarding/
│   ├── login/
│   ├── register/
│   ├── home/
│   ├── wallet/
│   ├── merchant_scan/
│   ├── profile/
│   └── settings/
├── services/                 # API and business logic
│   ├── api_client.dart
│   ├── auth_service.dart
│   └── wallet_service.dart
├── themes/                   # App theming
│   └── app_theme.dart
├── shared/                   # Shared utilities
└── widgets/                  # Reusable widgets
```

## State Management

Using **Riverpod** for state management:

- `authStateProvider` - Authentication state
- `walletStateProvider` - Wallet and transactions
- `apiClientProvider` - HTTP client

## Features

- [x] Splash Screen
- [x] Onboarding Flow
- [x] Login / Register
- [x] Home Dashboard
- [x] Wallet View
- [ ] QR Code Scanner (TODO)
- [x] Profile Screen
- [x] Settings Screen

## Testing

```bash
# Run all tests
flutter test

# Run with coverage
flutter test --coverage

# Run specific test
flutter test test/features/login/login_screen_test.dart
```

## Building

### Android

```bash
# Debug APK
flutter build apk --debug

# Release APK
flutter build apk --release

# App Bundle (for Play Store)
flutter build appbundle
```

### iOS

```bash
# Debug build
flutter build ios --debug

# Release build
flutter build ios --release

# Archive for App Store
flutter build ipa
```

## Fastlane

Fastlane is configured for automated builds and deployments.

### Android

```bash
cd android
fastlane android beta  # Deploy to internal testing
fastlane android deploy # Deploy to Play Store
```

### iOS

```bash
cd ios
fastlane ios beta  # Deploy to TestFlight
fastlane ios deploy # Deploy to App Store
```

## TODO

- [ ] Implement QR code scanning with `mobile_scanner`
- [ ] Add biometric authentication
- [ ] Implement push notifications
- [ ] Add transaction confirmation animations
- [ ] Implement offline support
- [ ] Add localization support

## Contributing

1. Create a feature branch
2. Make your changes
3. Run tests
4. Submit a pull request

## License

MIT License - see LICENSE file for details.
