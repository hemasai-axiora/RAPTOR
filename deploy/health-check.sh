#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Post-Deployment Health Check Script
# ==============================================================================

set -euo pipefail

MAX_RETRIES=5
RETRY_DELAY=3

echo "=== Running Production Health Verification ==="

# 1. Verify Docker Containers are Running
for container in raptor-nginx raptor-web raptor-db; do
  STATUS=$(docker inspect --format '{{.State.Status}}' "${container}" 2>/dev/null || echo "not_found")
  if [ "${STATUS}" != "running" ]; then
    echo "ERROR: Container ${container} is not running! Status: ${STATUS}" >&2
    exit 1
  fi
  echo "✓ Container ${container} is running."
done

# 2. Verify MySQL Health & Connectivity from inside web container
echo "Testing MySQL connectivity from web container..."
docker exec raptor-web php -r "
require '/var/www/html/app/config/config.php';
require '/var/www/html/app/core/Database.php';
try {
    Database::getInstance()->getConnection();
    echo 'DB_CONNECTION_SUCCESS';
} catch (Exception \$e) {
    echo 'DB_ERROR: ' . \$e->getMessage();
    exit(1);
}
" || {
  echo "ERROR: Database connectivity test failed!" >&2
  exit 1
}
echo ""

# 3. Verify HTTPS Endpoint and Login Screen (Resolving to 127.0.0.1)
echo "Testing public HTTPS login endpoint..."
for ((i=1; i<=MAX_RETRIES; i++)); do
  HTTP_CODE=$(curl -k -s -o /tmp/health_body.html -w "%{http_code}" -L https://agsraptor.com/ --resolve agsraptor.com:443:127.0.0.1 || echo "000")
  
  if [ "${HTTP_CODE}" -eq 200 ]; then
    if ! grep -qi "Fatal error" /tmp/health_body.html && ! grep -qi "Database connection error" /tmp/health_body.html; then
      echo "✓ HTTPS endpoint returned HTTP 200 OK (Clean HTML output, zero fatal errors)"
      rm -f /tmp/health_body.html
      break
    fi
  fi
  
  if [ "$i" -eq "$MAX_RETRIES" ]; then
    echo "ERROR: HTTPS endpoint failed after ${MAX_RETRIES} attempts (HTTP ${HTTP_CODE})!" >&2
    cat /tmp/health_body.html 2>/dev/null || true
    rm -f /tmp/health_body.html
    exit 1
  fi
  
  echo "Retry ${i}/${MAX_RETRIES}: Received HTTP ${HTTP_CODE}. Waiting ${RETRY_DELAY}s..."
  sleep "${RETRY_DELAY}"
done

# 4. Verify Storage Volume Writability
echo "Testing storage volume writability..."
TEST_FILE="/var/www/html/storage/health_check_probe_${TIMESTAMP:-test}.tmp"
docker exec raptor-web touch "${TEST_FILE}"
docker exec raptor-web rm -f "${TEST_FILE}"
echo "✓ Storage volume is writable."

# 5. Execute Automated Integrity Test Suite
echo "Executing automated post-deployment checks..."
docker exec raptor-web php /var/www/html/tests/post_deployment_checks.php || {
  echo "ERROR: Post-deployment test suite failed!" >&2
  exit 1
}

echo "=== All Production Health Checks PASSED (100% HEALTHY) ==="
exit 0
