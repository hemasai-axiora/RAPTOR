#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Production Rollback Script
# Reverts /var/www/raptor/current symlink to the previous working release
# ==============================================================================

set -euo pipefail

BASE_DIR="/var/www/raptor"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"

echo "=== Initiating Emergency Rollback Protocol ==="

# Identify current and previous release directories
RELEASES=($(ls -td ${RELEASES_DIR}/*))

if [ ${#RELEASES[@]} -lt 2 ]; then
  echo "Error: Cannot rollback! No previous release directory found in ${RELEASES_DIR}."
  exit 1
fi

CURRENT_RELEASE=$(readlink -f "${CURRENT_LINK}" || echo "")
FAILED_RELEASE="${RELEASES[0]}"
PREVIOUS_RELEASE="${RELEASES[1]}"

echo "Current (Failed) Release: ${FAILED_RELEASE}"
echo "Rolling back to Previous Working Release: ${PREVIOUS_RELEASE}"

# 1. Switch Symlink back to Previous Release
ln -sfn "${PREVIOUS_RELEASE}" "${CURRENT_LINK}"
chown -h www-data:www-data "${CURRENT_LINK}"

# 2. Reload PHP-FPM and Nginx
sudo systemctl reload php8.3-fpm || sudo service php8.3-fpm reload
sudo systemctl reload nginx || sudo service nginx reload

# 3. Remove Failed Release Directory
echo "Removing failed release directory..."
rm -rf "${FAILED_RELEASE}"

echo "=== Rollback Successfully Completed! Active Release: ${PREVIOUS_RELEASE} ==="
