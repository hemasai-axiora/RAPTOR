#!/bin/bash
# Upload this to EC2 and run it via SSM
set -e

echo "=== Step 1: Updating Docker Nginx SSL cert to use valid domain cert ==="

# Backup current config
docker exec raptor-nginx cp /etc/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf.bak

# Replace self-signed cert with the valid ags.raptor.unaux.com LE cert
docker exec raptor-nginx sed -i \
  's|ssl_certificate /etc/letsencrypt/live/raptor/fullchain.pem;|ssl_certificate /etc/letsencrypt/live/ags.raptor.unaux.com/fullchain.pem;|g' \
  /etc/nginx/conf.d/default.conf

docker exec raptor-nginx sed -i \
  's|ssl_certificate_key /etc/letsencrypt/live/raptor/privkey.pem;|ssl_certificate_key /etc/letsencrypt/live/ags.raptor.unaux.com/privkey.pem;|g' \
  /etc/nginx/conf.d/default.conf

echo "=== Step 2: Testing Nginx config ==="
docker exec raptor-nginx nginx -t

echo "=== Step 3: Reloading Nginx ==="
docker exec raptor-nginx nginx -s reload

echo "=== Step 4: Verifying cert now in use ==="
sleep 2
openssl s_client -connect 98.94.227.211:443 -servername ags.raptor.unaux.com </dev/null 2>/dev/null | openssl x509 -noout -subject -dates

echo "=== DONE! Docker Nginx now serves valid Let's Encrypt cert ==="
echo "=== Next: Update DNS raptor.unaux.com → 98.94.227.211 then run certbot for that domain ==="
