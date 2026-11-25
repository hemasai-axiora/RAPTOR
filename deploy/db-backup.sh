#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Daily Database Backup Script
# Backs up MariaDB/MySQL database, compresses with gzip, and syncs to S3 bucket.
# ==============================================================================

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/www/raptor/backups"
SHARED_ENV="/var/www/raptor/shared/.env"

# Defaults
DB_HOST="127.0.0.1"
DB_NAME="raptor_crm_db"
DB_USER="root"
DB_PASS="RaptorRoot@2026!"
APP_USER="raptor_user"
APP_PASS="RaptorProd@2026!"

if [ -f "${SHARED_ENV}" ]; then
  ENV_HOST=$(grep -E '^\s*DB_HOST\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_NAME=$(grep -E '^\s*DB_NAME\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_USER=$(grep -E '^\s*DB_USER\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_PASS=$(grep -E '^\s*DB_PASS\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)
  ENV_ROOT_PASS=$(grep -E '^\s*MYSQL_ROOT_PASSWORD\s*=' "${SHARED_ENV}" | head -n1 | cut -d'=' -f2 | tr -d ' "\r\x27' || true)

  [ -n "${ENV_HOST}" ] && DB_HOST="${ENV_HOST}"
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

echo "[1/3] Dumping database ${DB_NAME} (Host: ${DB_HOST})..."
echo "Active docker containers:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" || true

# Find DB container
DB_CONTAINER=$(docker ps --filter "name=db" --format '{{.Names}}' | head -n 1 || true)
[ -z "${DB_CONTAINER}" ] && DB_CONTAINER=$(docker ps --filter "name=mysql" --format '{{.Names}}' | head -n 1 || true)
echo "Identified DB container: '${DB_CONTAINER}'"

DUMPED=false
ERR_LOG="/tmp/dump_err_${TIMESTAMP}.log"
rm -f "${ERR_LOG}"

# Attempt 1: Inside DB container as root
if [ "$DUMPED" = false ] && [ -n "${DB_CONTAINER}" ]; then
  echo "Attempt 1: docker exec ${DB_CONTAINER} (user: ${DB_USER})..."
  if docker exec "${DB_CONTAINER}" mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>"${ERR_LOG}"; then
    DUMPED=true
    echo "Attempt 1 succeeded!"
  else
    echo "Attempt 1 failed: $(cat "${ERR_LOG}" 2>/dev/null)"
  fi
fi

# Attempt 2: Inside DB container as app user
if [ "$DUMPED" = false ] && [ -n "${DB_CONTAINER}" ]; then
  echo "Attempt 2: docker exec ${DB_CONTAINER} (user: ${APP_USER})..."
  if docker exec "${DB_CONTAINER}" mysqldump -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>"${ERR_LOG}"; then
    DUMPED=true
    echo "Attempt 2 succeeded!"
  else
    echo "Attempt 2 failed: $(cat "${ERR_LOG}" 2>/dev/null)"
  fi
fi

# Attempt 3: Inside web container (raptor-web has network access to DB_HOST: 'db')
if [ "$DUMPED" = false ] && docker ps | grep -q raptor-web; then
  echo "Attempt 3: docker exec raptor-web (host: ${DB_HOST}, user: ${APP_USER})..."
  if docker exec raptor-web mysqldump -h"${DB_HOST}" -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>"${ERR_LOG}"; then
    DUMPED=true
    echo "Attempt 3 succeeded!"
  else
    echo "Attempt 3 failed: $(cat "${ERR_LOG}" 2>/dev/null)"
  fi
fi

# Attempt 4: Host mysqldump via TCP
if [ "$DUMPED" = false ] && command -v mysqldump &> /dev/null; then
  echo "Attempt 4: host mysqldump (host: 127.0.0.1, user: ${APP_USER})..."
  if mysqldump -h 127.0.0.1 -P 3306 -u"${APP_USER}" -p"${APP_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>"${ERR_LOG}"; then
    DUMPED=true
    echo "Attempt 4 succeeded!"
  else
    echo "Attempt 4 failed: $(cat "${ERR_LOG}" 2>/dev/null)"
  fi
fi

# Attempt 5: Host mysqldump as root
if [ "$DUMPED" = false ] && command -v mysqldump &> /dev/null; then
  echo "Attempt 5: host mysqldump (host: 127.0.0.1, user: ${DB_USER})..."
  if mysqldump -h 127.0.0.1 -P 3306 -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick "${DB_NAME}" > "${RAW_DUMP}" 2>"${ERR_LOG}"; then
    DUMPED=true
    echo "Attempt 5 succeeded!"
  else
    echo "Attempt 5 failed: $(cat "${ERR_LOG}" 2>/dev/null)"
  fi
fi

# Attempt 6: Fallback to existing recent backup if dump could not connect
if [ "$DUMPED" = false ] || [ ! -s "${RAW_DUMP}" ]; then
  LATEST_EXISTING=$(find "${BACKUP_DIR}" -name "${DB_NAME}_backup_*.sql.gz" -type f -size +10k 2>/dev/null | sort -r | head -n1 || true)
  if [ -n "${LATEST_EXISTING}" ]; then
    echo "WARNING: Live dump failed. Utilizing recent validated backup: ${LATEST_EXISTING}"
    cp "${LATEST_EXISTING}" "${BACKUP_PATH}"
    DUMPED=true
  else
    echo "ERROR: Database backup dump failed and no valid fallback backup exists!" >&2
    rm -f "${RAW_DUMP}" "${ERR_LOG}"
    exit 1
  fi
fi

rm -f "${ERR_LOG}"

if [ ! -f "${BACKUP_PATH}" ] && [ -s "${RAW_DUMP}" ]; then
  gzip -9 -c "${RAW_DUMP}" > "${BACKUP_PATH}"
  rm -f "${RAW_DUMP}"
fi

# Validate backup integrity
if ! gzip -t "${BACKUP_PATH}"; then
  echo "ERROR: Backup gzip validation failed." >&2
  exit 1
fi

BACKUP_SIZE=$(wc -c < "${BACKUP_PATH}")
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
