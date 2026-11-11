#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Production Rollback Script
# ==============================================================================

set -euo pipefail

BASE_DIR="/var/www/raptor"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"
PREV_RELEASE_FILE="${BASE_DIR}/previous_release"
LOG_FILE="${BASE_DIR}/deployments.log"

echo "======================================================================"
echo "RAPTOR CRM/HRMS EMERGENCY ROLLBACK PROTOCOL"
echo "Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')"
echo "======================================================================"

TARGET_RELEASE=""

# Check if previous_release record exists and is valid
if [ -f "${PREV_RELEASE_FILE}" ]; then
  CANDIDATE=$(cat "${PREV_RELEASE_FILE}")
  if [ -d "${CANDIDATE}" ]; then
    TARGET_RELEASE="${CANDIDATE}"
  fi
fi

# If not found from record, find second newest release in releases/
if [ -z "${TARGET_RELEASE}" ]; then
  RELEASES=($(ls -dt ${RELEASES_DIR}/*/ 2>/dev/null || true))
  if [ ${#RELEASES[@]} -ge 2 ]; then
    TARGET_RELEASE="${RELEASES[1]}"
  fi
fi

if [ -z "${TARGET_RELEASE}" ] || [ ! -d "${TARGET_RELEASE}" ]; then
  echo "CRITICAL ERROR: No valid previous release directory found to rollback to!" >&2
  exit 1
fi

echo "Rolling back current symlink to: ${TARGET_RELEASE}"
ln -sfn "${TARGET_RELEASE}" "${CURRENT_LINK}"

echo "Recreating raptor-web container..."
cd "${BASE_DIR}"
docker compose -f "${BASE_DIR}/docker-compose.prod.yml" up -d --no-deps --force-recreate web
docker exec raptor-nginx nginx -t
docker exec raptor-nginx nginx -s reload

echo "[$(date '+%Y-%m-%d %H:%M:%S IST')] ROLLBACK: Reverted active release to ${TARGET_RELEASE}" >> "${LOG_FILE}"

echo "Executing health check on rolled-back release..."
"${BASE_DIR}/health-check.sh"

echo "======================================================================"
echo "ROLLBACK COMPLETED SUCCESSFULLY. ACTIVE RELEASE: ${TARGET_RELEASE}"
echo "======================================================================"
