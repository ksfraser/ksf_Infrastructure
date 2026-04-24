#!/bin/bash
# KSF Post-Install Script
# Usage: bash post-install.sh [fqdn]
#   fqdn - Optional FQDN or IP (defaults to hostname -I)

set -e

# Get FQDN from env var or default to hostname -I
FQDN=${KSF_URL:-$(hostname -I | awk '{print $1}'})
WP_URL="http://${FQDN}:8081"
FA_URL="http://${FQDN}:8080"

echo "=== KSF Post-Install ==="
echo "FQDN: $FQDN"
echo "WP URL: $WP_URL"
echo "FA URL: $FA_URL"

# Update WordPress to latest
echo "Updating WordPress..."
podman exec ksf-wp wp core update --allow-root || true

# Update WordPress site URL to FQDN
echo "Setting WordPress URLs to $WP_URL..."
podman exec ksf-wp wp option update siteurl "$WP_URL" --allow-root || true
podman exec ksf-wp wp option update home "$WP_URL" --allow-root || true

# Install WooCommerce if not already installed
echo "Installing WooCommerce..."
podman exec ksf-wp wp plugin install woocommerce --activate --allow-root 2>/dev/null || echo "WooCommerce already installed"

# Configure FA only if not already configured (no config_db.php means user needs to run install wizard)
if podman exec ksf-fa test -f /var/www/html/config_db.php 2>/dev/null; then
  echo "FA already configured, skipping..."
else
  echo "Configuring FA to connect to MariaDB (run install wizard if needed)..."
  podman exec ksf-fa bash -c "
    mkdir -p /var/www/html/company/0
    echo '<?php' > /var/www/html/config_db.php
    echo '\$db = mysqli_connect(\"ksf-mariadb\", \"ksf_user\", \"ksfuser2024!\", \"ksf_fa\");' >> /var/www/html/config_db.php
    echo 'if (!\$db) { die(\"Cannot connect\"); }' >> /var/www/html/config_db.php
    echo '\$Tbdfm = \"ksf_fa.0_\";' >> /var/www/html/config_db.php
    echo 'global \$db, \$Tbdfm;' >> /var/www/html/config_db.php
    echo '<?php' > /var/www/html/company/0/index.php
    echo '\$company = 0;' >> /var/www/html/company/0/index.php
    echo 'define(\"COMPANY_NO\", 0);' >> /var/www/html/company/0/index.php
    echo '\$db_connections = array(0 => array(\"tbdf\" => \"ksf_fa.0_\", \"name\" => \"KSF Company\"));' >> /var/www/html/company/0/index.php
    echo '<?php \$installed_extensions = array();' > /var/www/html/installed_extensions.php
    chown -R www-data:www-data /var/www/html/company /var/www/html/config_db.php /var/www/html/installed_extensions.php
  "
fi

echo "=== Post-Install Complete ==="
echo "FA: $FA_URL/install/"
echo "WP: $WP_URL/"