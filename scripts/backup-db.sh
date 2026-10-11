#!/usr/bin/env bash
#
# Backs up the MySQL database configured in the app .env file.
# Intended to run from cron on the VPS; see docs/VPS_SETUP_MM.md.
#
# Usage: scripts/backup-db.sh [backup-dir]
# Env:   BACKUP_KEEP_DAYS (default 14), MYSQLDUMP_BIN (default mysqldump)

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${1:-$PROJECT_DIR/storage/backups}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"

cd "$PROJECT_DIR"

if [[ ! -f .env ]]; then
    echo "Missing .env in $PROJECT_DIR" >&2
    exit 1
fi

env_value() {
    sed -n "s/^${1}=//p" .env | tail -n 1 | sed -e 's/^"//' -e 's/"$//'
}

if [[ "$(env_value DB_CONNECTION)" != "mysql" ]]; then
    echo "DB_CONNECTION is not mysql; this script backs up MySQL deployments only." >&2
    exit 1
fi

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"

mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$BACKUP_DIR/thai2d3d-$STAMP.sql.gz"

MYSQL_PWD="$DB_PASSWORD" "$MYSQLDUMP_BIN" \
    --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
    --single-transaction --quick --routines --no-tablespaces \
    "$DB_DATABASE" | gzip > "$FILE"

find "$BACKUP_DIR" -name 'thai2d3d-*.sql.gz' -mtime "+$KEEP_DAYS" -delete

echo "Backup written: $FILE"
