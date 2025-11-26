# Flutter Docker image for CI/CD builds
FROM ghcr.io/cirruslabs/flutter:stable

WORKDIR /app

# Copy pubspec files first for caching
COPY pubspec.yaml pubspec.lock ./

# Get dependencies
RUN flutter pub get

# Copy the rest of the application
COPY . .

# Build the app
CMD ["flutter", "build", "apk", "--debug"]
