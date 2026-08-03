#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Post-Deployment Health Check Script
# Validates local HTTP response, database connection, and PHP runtime health.
# ==============================================================================

set -euo pipefail

TARGET_URL="http://127.0.0.1/public/index.php?route=auth/login"
MAX_RETRIES=5
RETRY_INTERVAL=3

echo "=== Executing Post-Deployment Health Check ==="

for ((i=1; i<=MAX_RETRIES; i++)); do
  echo "Attempt ${i}/${MAX_RETRIES}: Checking ${TARGET_URL}..."
  
  STATUS_CODE=$(curl -s -o /tmp/health_response.txt -w "%{http_code}" --max-time 10 "${TARGET_URL}" || echo "000")
  
  if [ "${STATUS_CODE}" -eq 200 ] || [ "${STATUS_CODE}" -eq 302 ]; then
    # Ensure response does not contain fatal PHP stack traces
    if ! grep -qi "Fatal error" /tmp/health_response.txt && ! grep -qi "Database connection error" /tmp/health_response.txt; then
      echo "=== Health Check PASSED (HTTP ${STATUS_CODE}) ==="
      rm -f /tmp/health_response.txt
      exit 0
    fi
  fi
  
  echo "Health check failed (HTTP ${STATUS_CODE}). Retrying in ${RETRY_INTERVAL}s..."
  sleep ${RETRY_INTERVAL}
done

echo "Error: Health Check FAILED after ${MAX_RETRIES} attempts!"
cat /tmp/health_response.txt 2>/dev/null || true
rm -f /tmp/health_response.txt
exit 1
