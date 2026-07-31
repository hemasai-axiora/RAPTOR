#!/bin/bash
echo "=== Verifying created tables ==="
mysql -u raptor_user -pRaptorProd@2026! raptor_crm_db 2>/dev/null -e "SHOW TABLES;"
