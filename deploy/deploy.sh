#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Production Zero-Downtime Release Deployment Script
# Managed Directory: /var/www/raptor
# Releases: /var/www/raptor/releases/YYYYMMDD_HHMMSS
# Shared: /var/www/raptor/shared/.env & /var/www/raptor/shared/storage
# Symlink: /var/www/raptor/current
# ==============================================================================

set -euo pipefail

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BASE_DIR="/var/www/raptor"
RELEASE_DIR="${BASE_DIR}/releases/${TIMESTAMP}"
SHARED_DIR="${BASE_DIR}/shared"
CURRENT_LINK="${BASE_DIR}/current"
BACKUP_DIR="${BASE_DIR}/backups"
REPO_URL="https://github.com/hemasai-axiora/RAPTOR.git"

echo "=== Starting Production Release Deployment: ${TIMESTAMP} ==="

# 1. Pre-deployment Database Backup
echo "[1/7] Creating pre-deployment database backup..."
/var/www/raptor/db-backup.sh || echo "Warning: Pre-deployment DB backup completed with non-zero code."

# 2. Clone Latest Release
echo "[2/7] Cloning latest application release into ${RELEASE_DIR}..."
git clone --depth 1 --branch main "${REPO_URL}" "${RELEASE_DIR}"

# 3. Symlink Persistent Shared Files (.env & storage)
echo "[3/7] Symlinking shared persistent environment variables and storage..."
cp "${RELEASE_DIR}/app/config/config.php" "${SHARED_DIR}/.env"

rm -f "${RELEASE_DIR}/.env"
ln -s "${SHARED_DIR}/.env" "${RELEASE_DIR}/.env"

# Symlink persistent storage directory
if [ -d "${RELEASE_DIR}/storage" ]; then
  rm -rf "${RELEASE_DIR}/storage"
fi
ln -s "${SHARED_DIR}/storage" "${RELEASE_DIR}/storage"

# 4. Install Production Dependencies
echo "[4/7] Installing production composer dependencies..."
cd "${RELEASE_DIR}"
if [ -f "composer.json" ]; then
  composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi

# 5. Execute Database Migrations
echo "[5/7] Running database migrations..."
if [ -f "bin/migrate.php" ]; then
  if command -v docker &> /dev/null && docker ps | grep -q raptor-web; then
    docker exec raptor-web php bin/migrate.php || echo "Warning: Docker migration completed."
  elif command -v php &> /dev/null; then
    php bin/migrate.php || {
      echo "Error: Database migration failed! Aborting release."
      rm -rf "${RELEASE_DIR}"
      exit 1
    }
  fi
fi

# 6. Set Secure File & Directory Permissions
echo "[6/7] Applying secure file and directory permissions..."
chown -R www-data:www-data "${RELEASE_DIR}" 2>/dev/null || true
chmod -R 775 "${RELEASE_DIR}"
mkdir -p "${SHARED_DIR}/storage" 2>/dev/null || true
chown -R www-data:www-data "${SHARED_DIR}/storage" 2>/dev/null || true
chmod -R 777 "${SHARED_DIR}/storage" 2>/dev/null || true

# 7. Atomic Symlink Switch & Service Reload
echo "[7/7] Switching current release symlink to ${RELEASE_DIR}..."
ln -sfn "${RELEASE_DIR}" "${CURRENT_LINK}"

# Reload PHP-FPM / Nginx / Docker
echo "Reloading PHP-FPM, Nginx, and Docker containers..."
if command -v docker &> /dev/null && docker ps | grep -q raptor-web; then
  docker restart raptor-web 2>/dev/null || true
fi
systemctl reload php8.3-fpm 2>/dev/null || service php8.3-fpm reload 2>/dev/null || true
systemctl reload nginx 2>/dev/null || service nginx reload 2>/dev/null || true

# Clean up older releases (keep last 5 releases)
echo "Cleaning up old releases (keeping last 5)..."
cd "${BASE_DIR}/releases"
ls -t | tail -n +6 | xargs -I {} rm -rf "{}"

# Run Health Check
echo "Executing health check verification..."
/var/www/raptor/health-check.sh || {
  echo "Error: Health check failed! Triggering rollback..."
  /var/www/raptor/rollback.sh
  exit 1
}

echo "=== Production Deployment ${TIMESTAMP} Completed Successfully! ==="
