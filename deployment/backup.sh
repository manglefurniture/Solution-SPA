#!/usr/bin/env bash
set -euo pipefail

: "${APP_DIR:?APP_DIR is required}"
: "${BACKUP_DIR:?BACKUP_DIR is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_NAME:?DB_NAME is required}"
: "${DB_USER:?DB_USER is required}"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEST="$BACKUP_DIR/$STAMP"
mkdir -p "$DEST"
chmod 700 "$DEST"

cd "$APP_DIR"
git rev-parse HEAD > "$DEST/commit.txt"

MYSQL_PWD="${DB_PASSWORD:-}" mysqldump \
  -h "$DB_HOST" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USER" \
  --single-transaction \
  --routines \
  --triggers \
  "$DB_NAME" | gzip -9 > "$DEST/database.sql.gz"

tar \
  --exclude='.git' \
  --exclude='backend/config.php' \
  --exclude='backups' \
  -czf "$DEST/code.tar.gz" .

printf '%s\n' "$DEST" > "$BACKUP_DIR/latest"
echo "BACKUP_OK $DEST"
