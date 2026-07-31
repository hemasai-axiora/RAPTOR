#!/bin/bash
cd /var/www/html
echo "=== Step 1: Apply all migrations (restore schema+roles) ==="
php -d display_errors=1 bin/migrate.php 2>&1

echo ""
echo "=== Step 2: Clean and seed users ==="
php -d display_errors=1 bin/clean_and_seed.php 2>&1

echo ""
echo "Exit: $?"
