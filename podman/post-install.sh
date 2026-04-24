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

# Configure FA config for reverse proxy
echo "Configuring FA for reverse proxy..."
podman exec ksf-fa bash -c "
  echo '<?php' > /var/www/html/config_db.php
  echo '\$db = mysqli_connect(\"ksf-mariadb\", \"ksf_user\", \"ksfuser2024!\", \"ksf_fa\");' >> /var/www/html/config_db.php
  echo 'if (!\$db) { die(\"Cannot connect\"); }' >> /var/www/html/config_db.php
  echo '\$Tbdfm = \"ksf_fa.0_\";' >> /var/www/html/config_db.php
  echo 'global \$db, \$Tbdfm;' >> /var/www/html/config_db.php
  mkdir -p /var/www/html/company/0
  echo '<?php' > /var/www/html/company/0/index.php
  echo '\$company = 0;' >> /var/www/html/company/0/index.php
  echo 'define(\"COMPANY_NO\", 0);' >> /var/www/html/company/0/index.php
  echo '\$db_connections = array(0 => array(\"tbdf\" => \"ksf_fa.0_\", \"name\" => \"KSF Company\"));' >> /var/www/html/company/0/index.php
  echo '<?php \$installed_extensions = array();' > /var/www/html/installed_extensions.php
  chown -R www-data:www-data /var/www/html/company /var/www/html/config_db.php /var/www/html/installed_extensions.php
"

echo "=== Post-Install Complete ==="
echo "FA: $FA_URL/install/"
echo "WP: $WP_URL/"