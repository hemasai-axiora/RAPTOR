#!/bin/bash
echo "=== Web container connects to DB host: db ==="
echo "=== Checking tables from web container's MySQL connection ==="
mysql -h db -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null -e "SHOW TABLES LIKE 'account_%'; SHOW TABLES LIKE 'website_%'; SHOW TABLES LIKE 'customers';"
