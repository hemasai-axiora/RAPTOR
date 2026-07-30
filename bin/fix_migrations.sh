#!/bin/bash
echo "=== Removing stale 0031/0032 migration records ==="
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null \
  -e "DELETE FROM schema_migrations WHERE version IN ('0031_account_management_module', '0032_website_analytics_module');"

echo "=== Running migrations to create missing tables ==="
cd /var/www/html && php bin/migrate.php

echo ""
echo "=== Verifying account_sales + website_analytics tables ==="
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null \
  -e "SHOW TABLES LIKE 'account_%';"
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null \
  -e "SHOW TABLES LIKE 'website_%';"
