#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Daily Database Backup Script
# Backs up MariaDB/MySQL database, compresses with gzip, and syncs to S3 bucket.
# S3 Target: s3://app-frontend-hosting-dev-847013096108/database-backups/
# ==============================================================================

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/raptor/backups"
DB_NAME="${DB_NAME:-raptor_crm_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-RaptorRoot@2026!}"
S3_BUCKET="s3://raptor-crm-backups-847013096108/database-backups"

mkdir -p "${BACKUP_DIR}"

BACKUP_FILENAME="${DB_NAME}_backup_${TIMESTAMP}.sql.gz"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILENAME}"

echo "[1/3] Dumping database ${DB_NAME}..."
DB_CONTAINER=$(docker ps --filter "name=raptor-db" --format '{{.Names}}' | head -n 1)
if command -v docker &> /dev/null && [ -n "${DB_CONTAINER}" ]; then
  docker exec "${DB_CONTAINER}" mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" | gzip -9 > "${BACKUP_PATH}"
elif command -v mysqldump &> /dev/null; then
  mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick --lock-tables=false "${DB_NAME}" | gzip -9 > "${BACKUP_PATH}"
else
  echo "Neither docker nor mysqldump available; skipping local dump file creation."
  exit 1
fi

# Validate backup integrity
if ! gzip -t "${BACKUP_PATH}"; then
  echo "ERROR: Backup gzip validation failed." >&2
  exit 1
fi

BACKUP_SIZE=$(wc -c < "${BACKUP_PATH}")
if [ "${BACKUP_SIZE}" -lt 1000 ]; then
  echo "ERROR: Backup size too small (${BACKUP_SIZE} bytes), likely empty dump." >&2
  exit 1
fi

echo "[2/3] Uploading database backup to S3 (${S3_BUCKET})..."
if command -v aws &> /dev/null; then
  aws s3 cp "${BACKUP_PATH}" "${S3_BUCKET}/${BACKUP_FILENAME}" --only-show-errors || echo "Warning: S3 upload failed."
else
  echo "AWS CLI not found; skipping S3 backup upload."
fi

echo "[3/3] Rotating local backups (retaining last 7 days)..."
find "${BACKUP_DIR}" -name "${DB_NAME}_backup_*.sql.gz" -mtime +7 -exec rm -f {} \;

echo "=== Database Backup Completed: ${BACKUP_FILENAME} ==="
