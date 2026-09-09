#!/usr/bin/env bash
# Backup Craft database and uploads for Local Roots Africa.
# Usage: ./scripts/backup.sh [output_dir]

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT_DIR="${1:-${ROOT}/storage/backups}"
STAMP="$(date +%Y%m%d-%H%M%S)"
mkdir -p "$OUT_DIR"

cd "$ROOT"

if [ -f .env ]; then
  # shellcheck disable=SC1091
  set -a && source .env && set +a
fi

DB_FILE="${OUT_DIR}/db-${STAMP}.sql.gz"
UPLOADS_FILE="${OUT_DIR}/uploads-${STAMP}.tar.gz"

echo "Backing up database to ${DB_FILE}"
php craft db/backup "$DB_FILE"

if [ -d web/uploads ]; then
  echo "Backing up uploads to ${UPLOADS_FILE}"
  tar -czf "$UPLOADS_FILE" -C web uploads
fi

echo "Backup complete."
