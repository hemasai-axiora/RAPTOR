#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Daily Database Backup Script
# Backs up MariaDB/MySQL database, compresses with gzip, and syncs to S3 bucket.
# ==============================================================================

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/raptor/backups"
SHARED_ENV="/var/www/raptor/shared/.env"

# Read environment defaults if available
DB_NAME="${DB_NAME:-raptor_crm_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-RaptorRoot@2026!}"
APP_USER="raptor_user"
APP_PASS="RaptorProd@2026!"

if [ -f "${SHARED_ENV}" ]; then
  ENV_NAME=$(grep -E '^\s*DB_NAME\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_USER=$(grep -E '^\s*DB_USER\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_PASS=$(grep -E '^\s*DB_PASS\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_ROOT_PASS=$(grep -E '^\s*MYSQL_ROOT_PASSWORD\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)

  [ -n "${ENV_NAME}" ] && DB_NAME="${ENV_NAME}"
  [ -n "${ENV_USER}" ] && APP_USER="${ENV_USER}"
  [ -n "${ENV_PASS}" ] && APP_PASS="${ENV_PASS}"
  [ -n "${ENV_ROOT_PASS}" ] && DB_PASS="${ENV_ROOT_PASS}"
fi

mkdir -p "${BACKUP_DIR}"

BACKUP_FILENAME="${DB_NAME}_backup_${TIMESTAMP}.sql.gz"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILENAME}"
RAW_DUMP="/tmp/raptor_backup_${TIMESTAMP}.sql"
rm -f "${RAW_DUMP}"

echo "[1/3] Dumping database ${DB_NAME}..."

# Locate DB container
DB_CONTAINER=$(docker ps --filter "name=db" --format '{{.Names}}' | head -n 1 || true)
DUMPED=false

# Method 1: docker exec with root credentials
if [ -n "${DB_CONTAINER}" ]; then
  if docker exec "${DB_CONTAINER}" mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  # Method 2: docker exec with application credentials
  elif docker exec "${DB_CONTAINER}" mysqldump -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  fi
fi

# Method 3: host mysqldump via TCP 127.0.0.1
if [ "$DUMPED" = false ] && command -v mysqldump &> /dev/null; then
  if mysqldump -h 127.0.0.1 -P 3306 -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  elif mysqldump -h 127.0.0.1 -P 3306 -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  elif mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick --lock-tables=false "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  elif mysqldump -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick --lock-tables=false "${DB_NAME}" > "${RAW_DUMP}" 2>/dev/null; then
    DUMPED=true
  fi
fi

if [ "$DUMPED" = false ] || [ ! -s "${RAW_DUMP}" ]; then
  echo "ERROR: Database backup dump failed! Could not dump ${DB_NAME} via Docker or host mysqldump." >&2
  rm -f "${RAW_DUMP}"
  exit 1
fi

gzip -9 -c "${RAW_DUMP}" > "${BACKUP_PATH}"
rm -f "${RAW_DUMP}"

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

echo "[2/3] Uploading database backup to S3..."
ACCOUNT_ID=$(aws sts get-caller-identity --query "Account" --output text 2>/dev/null || echo "642122109154")
S3_BUCKET="s3://raptor-crm-backups-${ACCOUNT_ID}/database-backups"

if command -v aws &> /dev/null; then
  aws s3 cp "${BACKUP_PATH}" "${S3_BUCKET}/${BACKUP_FILENAME}" --only-show-errors || echo "Warning: S3 upload failed; local backup preserved."
else
  echo "AWS CLI not found; skipping S3 backup upload."
fi

echo "[3/3] Rotating local backups (retaining last 7 days)..."
find "${BACKUP_DIR}" -name "${DB_NAME}_backup_*.sql.gz" -mtime +7 -exec rm -f {} \;

echo "=== Database Backup Completed: ${BACKUP_FILENAME} (${BACKUP_SIZE} bytes) ==="
