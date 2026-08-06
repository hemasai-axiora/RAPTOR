#!/usr/bin/env bash
# ==============================================================================
# Generate Self-Signed SSL Certificate for EC2 Direct IP (98.94.227.211)
# ==============================================================================

set -euo pipefail

echo "[1/3] Creating SSL certificate directory at /etc/letsencrypt/live/raptor..."
sudo mkdir -p /etc/letsencrypt/live/raptor

echo "[2/3] Generating 2048-bit RSA Self-Signed SSL Certificate..."
sudo openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/letsencrypt/live/raptor/privkey.pem \
  -out /etc/letsencrypt/live/raptor/fullchain.pem \
  -subj "/C=IN/ST=Telangana/L=Hyderabad/O=Raptor CRM/OU=IT/CN=98.94.227.211"

sudo chmod 600 /etc/letsencrypt/live/raptor/privkey.pem
sudo chmod 644 /etc/letsencrypt/live/raptor/fullchain.pem

echo "[3/3] Reloading Nginx Web Server..."
if command -v nginx &> /dev/null; then
    sudo nginx -t && sudo systemctl reload nginx || true
fi

echo "=== Self-Signed SSL Certificate for 98.94.227.211 Generated Successfully ==="
