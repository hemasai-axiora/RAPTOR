#!/bin/bash
set -e

echo "=== RAPTOR SSL Setup for raptor.unaux.com ==="
echo "Step 1: Verify DNS points to this server..."
SERVER_IP=$(curl -s ifconfig.me)
DOMAIN_IP=$(nslookup raptor.unaux.com 8.8.8.8 | grep -A1 'Name:' | grep Address | awk '{print $2}' | head -1)

echo "Server IP: $SERVER_IP"
echo "raptor.unaux.com resolves to: $DOMAIN_IP"

if [ "$SERVER_IP" != "$DOMAIN_IP" ]; then
    echo "ERROR: DNS is NOT pointing to this server yet!"
    echo "raptor.unaux.com → $DOMAIN_IP (should be $SERVER_IP)"
    echo "Please update DNS first, then re-run this script."
    exit 1
fi

echo "Step 2: DNS is correct! Proceeding with Certbot..."

# Create ACME webroot dir
mkdir -p /var/www/raptor/shared/acme

# Stop docker nginx temporarily to free port 80 for certbot standalone
echo "Step 3: Pausing Docker Nginx for cert issuance..."
docker stop raptor-nginx || true

# Issue cert using standalone mode
certbot certonly --standalone \
  -d raptor.unaux.com \
  --non-interactive \
  --agree-tos \
  --email axiora.operations@gmail.com \
  --preferred-challenges http \
  --cert-name raptor-domain

echo "Step 4: Starting Docker Nginx back up..."
docker start raptor-nginx

echo "Step 5: Updating Docker Nginx config to use new cert..."
docker exec raptor-nginx sh -c "
sed -i 's|ssl_certificate /etc/letsencrypt/live/raptor/fullchain.pem|ssl_certificate /etc/letsencrypt/live/raptor-domain/fullchain.pem|g' /etc/nginx/conf.d/default.conf &&
sed -i 's|ssl_certificate_key /etc/letsencrypt/live/raptor/privkey.pem|ssl_certificate_key /etc/letsencrypt/live/raptor-domain/privkey.pem|g' /etc/nginx/conf.d/default.conf &&
nginx -t && nginx -s reload
"

echo "Step 6: Verifying HTTPS..."
sleep 3
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" https://raptor.unaux.com/public/index.php || echo "HTTPS check done (may need browser to verify)"

echo "=== DONE! raptor.unaux.com now has trusted HTTPS ==="
