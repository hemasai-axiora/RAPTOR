#!/bin/bash
echo "=== Removing stale migration records for 0031 and 0032 ==="
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null \
  -e "DELETE FROM schema_migrations WHERE version IN ('0031_account_management_module', '0032_website_analytics_module');"
echo "Done. Deleted rows: $?"

echo ""
echo "=== Running migrations ==="
cd /var/www/html && php bin/migrate.php

echo ""
echo "=== Verifying tables created ==="
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null \
  -e "SHOW TABLES LIKE 'account_%'; SHOW TABLES LIKE 'website_%';"
