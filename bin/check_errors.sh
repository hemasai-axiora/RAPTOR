#!/bin/bash
cd /var/www/html
touch /tmp/php_err.log
chmod 777 /tmp/php_err.log
truncate -s 0 /tmp/php_err.log

echo "=== account_sales/index ==="
REQUEST_METHOD=GET QUERY_STRING=route=account_sales/index \
  php -d display_errors=0 -d log_errors=1 -d error_log=/tmp/php_err.log \
  public/index.php > /dev/null 2>/tmp/stderr.log
echo "Exit: $?"
echo "--- error_log ---"
cat /tmp/php_err.log
echo "--- stderr ---"
cat /tmp/stderr.log

truncate -s 0 /tmp/php_err.log
echo ""
echo "=== website_analytics/index ==="
REQUEST_METHOD=GET QUERY_STRING=route=website_analytics/index \
  php -d display_errors=0 -d log_errors=1 -d error_log=/tmp/php_err.log \
  public/index.php > /dev/null 2>/tmp/stderr.log
echo "Exit: $?"
echo "--- error_log ---"
cat /tmp/php_err.log
echo "--- stderr ---"
cat /tmp/stderr.log
