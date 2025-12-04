#!/usr/bin/env bash

set -euo pipefail
IFS=$'\n\t'

log() { echo "[laravel] $*"; }
warn() { echo "[laravel][warn] $*" >&2; }
die() { echo "[laravel][error] $*" >&2; exit 1; }

REPO_ROOT_DIR="$(cd "$(dirname "$0")"/.. && pwd)"
API_DIR="$REPO_ROOT_DIR/services/api"

require_cmd() {
  local cmd="$1"
  command -v "$cmd" >/dev/null 2>&1 || die "Required command not found: $cmd"
}

create_laravel_app() {
  if [[ -d "$API_DIR" && -f "$API_DIR/artisan" ]]; then
    log "Laravel app already exists at services/api"
    return 0
  fi

  mkdir -p "$REPO_ROOT_DIR/services" || die "Failed to ensure services directory"

  log "Creating Laravel app in services/api..."
  # Try Laravel 10.*, fallback to latest stable
  if composer create-project --prefer-dist "laravel/laravel" "$API_DIR" "10.*"; then
    log "Created Laravel 10.* project successfully."
  else
    warn "Failed to create Laravel 10.*; falling back to latest stable."
    composer create-project --prefer-dist "laravel/laravel" "$API_DIR" || die "Failed to create Laravel project"
  fi
}

setup_env() {
  pushd "$API_DIR" >/dev/null || die "Cannot cd to $API_DIR"
  if [[ -f .env ]]; then
    log ".env already exists; leaving as-is"
  else
    if [[ -f .env.example ]]; then
      log "Creating .env from .env.example"
      cp .env.example .env || die "Failed to copy .env.example"
    else
      warn ".env.example not found; creating minimal .env"
      cat > .env <<'EOF'
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=sqlite
EOF
    fi
  fi

  # Ensure sqlite default config entries and path
  local sqlite_path="$(pwd)/database/database.sqlite"
  mkdir -p database || die "Failed to create database directory"
  if [[ ! -f "$sqlite_path" ]]; then
    log "Creating sqlite database file: $sqlite_path"
    : > "$sqlite_path" || die "Failed to create sqlite file"
  else
    log "sqlite database file exists: $sqlite_path"
  fi

  # Update .env entries for sqlite and app key if missing
  if grep -q '^DB_CONNECTION=' .env; then
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
  else
    echo 'DB_CONNECTION=sqlite' >> .env
  fi

  # Remove conflicting DB settings for sqlite
  sed -i '/^DB_HOST=/d' .env || true
  sed -i '/^DB_PORT=/d' .env || true
  sed -i '/^DB_DATABASE=/d' .env || true
  sed -i '/^DB_USERNAME=/d' .env || true
  sed -i '/^DB_PASSWORD=/d' .env || true
  echo "DB_DATABASE=\"$sqlite_path\"" >> .env

  popd >/dev/null
}

install_sanctum_and_publish() {
  pushd "$API_DIR" >/dev/null || die "Cannot cd to $API_DIR"
  log "Installing Laravel Sanctum..."
  composer require laravel/sanctum || die "Failed to install Sanctum"
  log "Publishing Sanctum provider..."
  php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --force || warn "Sanctum publish may have already been done"
  popd >/dev/null
}

generate_key_and_migrate() {
  pushd "$API_DIR" >/dev/null || die "Cannot cd to $API_DIR"
  log "Generating APP_KEY..."
  php artisan key:generate || warn "Key generation failed; ensure PHP extensions are installed"

  log "Running migrations with sqlite (if configured)..."
  if php artisan migrate --force; then
    log "Migrations completed."
  else
    warn "Migrations failed; check database configuration and PHP extensions."
  fi
  popd >/dev/null
}

print_next_steps() {
  cat <<'EOF'

Next steps:
- Start the local development server:
  cd services/api && php artisan serve

- If you prefer a different DB, update services/api/.env accordingly and run:
  cd services/api && php artisan migrate --force

- Sanctum is installed; you can now configure API authentication.
EOF
}

main() {
  log "Validating prerequisites..."
  require_cmd composer
  require_cmd php

  create_laravel_app
  setup_env
  install_sanctum_and_publish
  generate_key_and_migrate
  print_next_steps
  log "Laravel bootstrap complete."
}

main "$@"
