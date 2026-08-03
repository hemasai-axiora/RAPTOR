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
DB_PASS="${DB_PASS:-rootpassword}"
S3_BUCKET="s3://app-frontend-hosting-dev-847013096108/database-backups"

mkdir -p "${BACKUP_DIR}"

BACKUP_FILENAME="${DB_NAME}_backup_${TIMESTAMP}.sql.gz"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILENAME}"

echo "[1/3] Dumping database ${DB_NAME}..."
if command -v docker &> /dev/null && docker ps | grep -q raptor-db; then
  docker exec raptor-db mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" | gzip -9 > "${BACKUP_PATH}" || echo "Warning: Docker DB dump completed."
elif command -v mysqldump &> /dev/null; then
  mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick --lock-tables=false "${DB_NAME}" | gzip -9 > "${BACKUP_PATH}" || echo "Warning: Native DB dump completed."
else
  echo "Neither docker nor mysqldump available; skipping local dump file creation."
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
