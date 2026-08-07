#!/bin/bash
echo "=== NGINX VERSION ==="
nginx -v 2>&1

echo "=== CERTBOT VERSION ==="
certbot --version 2>&1 || echo "Certbot not installed"

echo "=== LETSENCRYPT CERTS ==="
ls /etc/letsencrypt/live/ 2>&1

echo "=== NGINX ENABLED SITES ==="
ls /etc/nginx/sites-enabled/ 2>&1

echo "=== NGINX RAPTOR CONF ==="
cat /etc/nginx/sites-enabled/raptor.conf 2>/dev/null || cat /etc/nginx/sites-available/raptor.conf 2>/dev/null || echo "No raptor.conf found"

echo "=== NGINX DEFAULT CONF ==="
cat /etc/nginx/sites-enabled/default 2>/dev/null | head -30 || echo "No default site"

echo "=== PUBLIC IP ==="
curl -s ifconfig.me

echo "=== APP ENV ==="
cat /var/www/raptor/shared/.env 2>/dev/null || cat /var/www/raptor/current/.env 2>/dev/null || echo "No .env found"

echo "=== DONE ==="
