#!/usr/bin/env bash
# ==============================================================================
# RAPTOR CRM & HRMS — Production Server Setup Script (Ubuntu LTS)
# Installs: PHP 8.3, PHP-FPM, Nginx, MariaDB, Certbot, CloudWatch Agent, UFW
# Target Path: /var/www/raptor
# ==============================================================================

set -euo pipefail

echo "[1/8] Updating Ubuntu system packages..."
sudo apt-get update -y
sudo apt-get upgrade -y
sudo apt-get install -y software-properties-common curl wget git unzip zip ufw ca-certificates gnupg

echo "[2/8] Adding PHP 8.3 Repository (ondrej/php)..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update -y

echo "[3/8] Installing PHP 8.3 and required extensions..."
sudo apt-get install -y \
  php8.3-fpm \
  php8.3-cli \
  php8.3-common \
  php8.3-mysql \
  php8.3-curl \
  php8.3-xml \
  php8.3-mbstring \
  php8.3-zip \
  php8.3-gd \
  php8.3-opcache \
  php8.3-bcmath

echo "[4/8] Installing Web Server & Database (Nginx & MariaDB)..."
sudo apt-get install -y nginx mariadb-server certbot python3-certbot-nginx

echo "[5/8] Installing Composer globally..."
if ! command -v composer &> /dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "[6/8] Installing Amazon CloudWatch Agent & AWS CLI..."
if ! command -v aws &> /dev/null; then
  sudo apt-get install -y awscli amazon-cloudwatch-agent
fi

echo "[7/8] Provisioning Release Directory Structure at /var/www/raptor..."
sudo mkdir -p /var/www/raptor/releases
sudo mkdir -p /var/www/raptor/shared/storage
sudo mkdir -p /var/www/raptor/backups

# Ensure www-data ownership
sudo chown -R www-data:www-data /var/www/raptor
sudo chmod -R 775 /var/www/raptor

echo "[8/8] Configuring UFW Firewall Rules..."
sudo ufw allow 80/tcp comment 'HTTP Web'
sudo ufw allow 443/tcp comment 'HTTPS Web'
# Port 22 is disabled publicly for security; AWS SSM Session Manager is used for administration.
sudo ufw deny 3306/tcp comment 'MySQL Internal Only'
echo "y" | sudo ufw enable

echo "=== EC2 Server Setup Completed Successfully ==="
