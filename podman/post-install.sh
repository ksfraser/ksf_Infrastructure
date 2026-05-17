#!/bin/bash
# KSF Post-Install Script
# Usage: bash post-install.sh [fqdn]
#   fqdn - Optional FQDN or IP (defaults to hostname -I)

set -e

FQDN=${KSF_URL:-$(hostname -I | awk '{print $1}')}
WP_URL="http://${FQDN}:8091"
FA_URL="http://${FQDN}:8090"

echo "=== KSF Post-Install ==="
echo "FQDN: $FQDN"
echo "WP URL: $WP_URL"
echo "FA URL: $FA_URL"

echo "Installing WP-CLI..."
podman exec ksf-wp bash -c "command -v wp >/dev/null 2>&1 || { curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp; }" || true

echo "Checking WordPress installation..."
if podman exec ksf-wp wp --allow-root core is-installed 2>/dev/null; then
  echo "WordPress already installed, updating URLs..."
else
  echo "Installing WordPress..."
  podman exec ksf-wp wp --allow-root core install \
    --url="$WP_URL" \
    --title="KSF Customer Portal" \
    --admin_user=admin \
    --admin_password=admin2024! \
    --admin_email=admin@example.com
fi

echo "Setting WordPress URLs..."
podman exec ksf-wp wp option update siteurl "$WP_URL" --allow-root || true
podman exec ksf-wp wp option update home "$WP_URL" --allow-root || true

echo "Installing WooCommerce..."
podman exec ksf-wp wp plugin install woocommerce --activate --allow-root 2>/dev/null || echo "WooCommerce already installed"

echo "=== Post-Install Complete ==="
echo "FA: $FA_URL/install/"
echo "WP: $WP_URL/"