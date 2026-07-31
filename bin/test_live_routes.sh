#!/bin/bash
cd /tmp
echo "1. Fetching login page & CSRF token..."
CSRF=$(curl -s -c cookies.txt http://localhost:80/public/index.php?route=auth/login | grep -oP 'name="csrf_token" value="\K[^"]+')
echo "CSRF Token: $CSRF"

echo "2. Logging in..."
curl -s -b cookies.txt -c cookies.txt -X POST \
  -d "email=admin@raptor.local&password=Raptor@12345&csrf_token=$CSRF" \
  http://localhost:80/public/index.php?route=auth/login > /dev/null

echo "3. Testing account_sales/index..."
CODE1=$(curl -s -b cookies.txt -c cookies.txt -o /dev/null -w "%{http_code}" http://localhost:80/public/index.php?route=account_sales/index)
echo "account_sales/index HTTP Status: $CODE1"

echo "4. Testing website_analytics/index..."
CODE2=$(curl -s -b cookies.txt -c cookies.txt -o /dev/null -w "%{http_code}" http://localhost:80/public/index.php?route=website_analytics/index)
echo "website_analytics/index HTTP Status: $CODE2"
