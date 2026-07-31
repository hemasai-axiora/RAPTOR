#!/bin/bash
cd /var/www/html
echo "=== Running clean_and_seed.php with line-by-line output ==="
php -d display_errors=1 -d error_reporting=32767 bin/clean_and_seed.php 2>&1
echo "PHP_EXIT: $?"
echo "Last lines of output above"
