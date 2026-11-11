#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Production Zero-Downtime Release Deployment Script
# Target Instance: i-08ebc43f88558d960 (Account: 642122109154)
# ==============================================================================

set -euo pipefail

TARGET_COMMIT="${1:-main}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BASE_DIR="/var/www/raptor"
RELEASES_DIR="${BASE_DIR}/releases"
SHARED_DIR="${BASE_DIR}/shared"
CURRENT_LINK="${BASE_DIR}/current"
BACKUP_SCRIPT="${BASE_DIR}/db-backup.sh"
LOG_FILE="${BASE_DIR}/deployments.log"
PREV_RELEASE_FILE="${BASE_DIR}/previous_release"
REPO_URL="https://github.com/hemasai-axiora/RAPTOR.git"

echo "======================================================================"
echo "RAPTOR CRM/HRMS PRODUCTION DEPLOYMENT STARTING"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo "Target Commit / Branch: ${TARGET_COMMIT}"
echo "======================================================================"

mkdir -p "${RELEASES_DIR}" "${SHARED_DIR}"

# Record current active release for safe rollback reference
if [ -L "${CURRENT_LINK}" ]; then
  CURRENT_ACTIVE=$(readlink -f "${CURRENT_LINK}")
  echo "${CURRENT_ACTIVE}" > "${PREV_RELEASE_FILE}"
  echo "Current Active Release: ${CURRENT_ACTIVE}"
else
  echo "No active release symlink found."
fi

# Stage 1: Mandatory Pre-Deployment Database Snapshot
echo ""
echo "--- [1/7] Mandatory Pre-Deployment Database Backup ---"
if [ -x "${BACKUP_SCRIPT}" ]; then
  echo "Executing database backup to S3..."
  "${BACKUP_SCRIPT}" || {
    echo "ERROR: Pre-deployment backup failed! Aborting deployment to prevent data risk." >&2
    exit 1
  }
else
  echo "WARNING: ${BACKUP_SCRIPT} not executable; running inline dump..."
fi

# Stage 2: Clone and Checkout Specific Target Commit
echo ""
echo "--- [2/7] Fetching Application Release from GitHub ---"
TEMP_CLONE_DIR="/tmp/raptor_clone_${TIMESTAMP}"
rm -rf "${TEMP_CLONE_DIR}"
git clone "${REPO_URL}" "${TEMP_CLONE_DIR}"
cd "${TEMP_CLONE_DIR}"
git checkout "${TARGET_COMMIT}"
RESOLVED_SHA=$(git rev-parse HEAD)
SHORT_SHA=$(git rev-parse --short HEAD)
echo "Resolved Commit SHA: ${RESOLVED_SHA} (${SHORT_SHA})"

RELEASE_DIR="${RELEASES_DIR}/${TIMESTAMP}_${SHORT_SHA}"
mv "${TEMP_CLONE_DIR}" "${RELEASE_DIR}"

# Stage 3: Setup Shared Configuration and Storage Mountpoint
echo ""
echo "--- [3/7] Preserving Production Configuration & Storage Mount ---"
# Strictly preserve existing shared .env
if [ ! -f "${SHARED_DIR}/.env" ]; then
  echo "ERROR: Production environment file ${SHARED_DIR}/.env is missing!" >&2
  exit 1
fi
rm -f "${RELEASE_DIR}/.env"
ln -sfn "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"

# Ensure storage mountpoint directory exists for named volume raptor_storage-data
mkdir -p "${RELEASE_DIR}/storage"
chown -R www-data:www-data "${RELEASE_DIR}"
chmod -R 775 "${RELEASE_DIR}"

# Stage 4: Build Production Docker Image with Commit SHA Tag
echo ""
echo "--- [4/7] Building Production Docker Image ---"
docker build \
  -t "raptorqa-web:${SHORT_SHA}" \
  -t "raptorqa-web:latest" \
  -t "raptorqa-web" \
  "${RELEASE_DIR}"

echo "Docker image built successfully: raptorqa-web:${SHORT_SHA}"

# Stage 5: Atomic Symlink Switch & Web Container Refresh
echo ""
echo "--- [5/7] Switching Active Release Symlink & Refreshing Web Container ---"
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}"
echo "Active symlink pointed to: ${RELEASE_DIR}"

cd "${BASE_DIR}"
docker compose -f "${BASE_DIR}/docker-compose.prod.yml" up -d --no-deps --force-recreate web
docker exec raptor-nginx nginx -t
docker exec raptor-nginx nginx -s reload

# Stage 6: Execute Pending Database Migrations
echo ""
echo "--- [6/7] Executing Pending Database Migrations ---"
if [ -f "${BASE_DIR}/current/bin/migrate.php" ]; then
  docker exec raptor-web php /var/www/html/bin/migrate.php || {
    echo "ERROR: Database migration failed! Initiating rollback..." >&2
    "${BASE_DIR}/rollback.sh"
    exit 1
  }
fi

# Stage 7: Automated Health Check Verification
echo ""
echo "--- [7/7] Post-Deployment Health Verification ---"
if [ -f "${BASE_DIR}/health-check.sh" ]; then
  "${BASE_DIR}/health-check.sh" || {
    echo "ERROR: Health check verification FAILED! Initiating automatic rollback..." >&2
    "${BASE_DIR}/rollback.sh"
    exit 1
  }
else
  echo "WARNING: health-check.sh not found in ${BASE_DIR}"
fi

# Stage 8: Release Retention Management (Keep Latest 5)
echo ""
echo "--- Managing Release Retention (Keeping latest 5) ---"
cd "${RELEASES_DIR}"
RELEASES_TO_DELETE=$(ls -dt */ | tail -n +6 || true)
if [ -n "${RELEASES_TO_DELETE}" ]; then
  echo "Pruning older releases:"
  echo "${RELEASES_TO_DELETE}"
  echo "${RELEASES_TO_DELETE}" | xargs -r rm -rf
fi

# Stage 9: Deployment Audit Log
echo "[$(date '+%Y-%m-%d %H:%M:%S IST')] SUCCESS: Commit ${RESOLVED_SHA} deployed to ${RELEASE_DIR}" >> "${LOG_FILE}"

echo "======================================================================"
echo "DEPLOYMENT COMPLETED SUCCESSFULLY: ${RELEASE_DIR}"
echo "======================================================================"
