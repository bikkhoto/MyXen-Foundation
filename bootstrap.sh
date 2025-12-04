#!/usr/bin/env bash

# MyXen Monorepo Bootstrap Script
# Sets up the initial repo structure, README, .gitignore, and basic services skeleton.

set -euo pipefail
IFS=$'\n\t'

SCRIPT_NAME="bootstrap.sh"

log() {
  echo "[bootstrap] $*"
}

warn() {
  echo "[bootstrap][warn] $*" >&2
}

die() {
  echo "[bootstrap][error] $*" >&2
  exit 1
}

ensure_dir() {
  local dir="$1"
  if [[ -d "$dir" ]]; then
    log "Directory exists: $dir"
  else
    log "Creating directory: $dir"
    mkdir -p "$dir" || die "Failed to create directory: $dir"
  fi
}

write_file_if_absent() {
  local path="$1"
  local content="$2"
  if [[ -f "$path" ]]; then
    warn "File already exists, leaving untouched: $path"
  else
    log "Creating file: $path"
    printf "%s\n" "$content" > "$path" || die "Failed to write file: $path"
  fi
}

init_git_repo() {
  if git rev-parse --git-dir > /dev/null 2>&1; then
    log "Git repository already initialized."
  else
    log "Initializing new git repository..."
    git init || die "git init failed"
  fi

  # Ensure main branch exists without pushing
  local current_branch
  current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")

  # Try to create/switch to main if it doesn't exist
  local switched_via_orphan=0

  if git show-ref --verify --quiet refs/heads/main; then
    log "Branch 'main' exists."
  else
    log "Creating branch 'main'..."
    # If repository has no commits yet, create an orphan branch 'main' without committing
    if ! git rev-parse --verify HEAD >/dev/null 2>&1; then
      log "No commits yet; creating orphan branch 'main' (no commit)."
      git checkout --orphan main || die "Failed to create orphan branch 'main'"
      switched_via_orphan=1
    else
      git branch main || die "Failed to create branch 'main'"
    fi
  fi

  # Switch to main if not already on it
  if [[ "$switched_via_orphan" -eq 1 ]]; then
    log "On orphan branch 'main'"
  else
    current_branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "")
    if [[ "$current_branch" != "main" ]]; then
      log "Switching to branch 'main'"
      git checkout main || die "Failed to checkout 'main'"
    else
      log "Already on branch 'main'"
    fi
  fi
}

create_readme_content() {
  cat << 'EOF'
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

EOF
}

create_gitignore_content() {
  cat << 'EOF'
# MyXen Monorepo .gitignore

# PHP/Laravel
vendor/
/storage/
.env

# Node/JS
node_modules/

# IDE/Editor
.idea/
.vscode/

# OS/General
.DS_Store
Thumbs.db

EOF
}

main() {
  log "Starting MyXen monorepo bootstrap..."

  # Top-level directories
  for d in services mobile web infra docs devops scripts; do
    ensure_dir "$d"
  done

  # Service skeleton directories
  for s in auth-service kyc-service payments-service admin-panel; do
    ensure_dir "services/$s"
  done

  # README.md
  local readme
  readme="$(create_readme_content)"
  write_file_if_absent "README.md" "$readme"

  # .gitignore
  local gitignore
  gitignore="$(create_gitignore_content)"
  write_file_if_absent ".gitignore" "$gitignore"

  # Initialize git and ensure main branch
  init_git_repo

  log "Bootstrap complete."
  echo
  echo "Next steps:"
  echo "1) Create a new Laravel API skeleton (optional example):"
  echo "   composer create-project laravel/laravel services/api"
  echo ""
  echo "2) Re-run the bootstrap script anytime to ensure structure:"
  echo "   ./bootstrap.sh"
  echo ""
  echo "Tip: mark the script executable if needed:"
  echo "   chmod +x ./bootstrap.sh"
}

main "$@"
