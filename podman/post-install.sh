#!/bin/bash
# KSF Post-Install Script
# Run after containers are up with: bash post-install.sh

set -e

echo "=== KSF Post-Install ==="

# Get the host LAN IP for WordPress URLs
HOST_IP=$(hostname -I | awk '{print $1}')
WP_URL="http://${HOST_IP}:8081"

# Update WordPress to latest
echo "Updating WordPress..."
podman exec ksf-wp wp core update --allow-root || true

# Set WordPress site URL to host IP
echo "Setting WordPress URLs to $WP_URL..."
podman exec ksf-wp wp option update siteurl "$WP_URL" --allow-root || true
podman exec ksf-wp wp option update home "$WP_URL" --allow-root || true

# Install WooCommerce if not already installed
echo "Installing WooCommerce..."
podman exec ksf-wp wp plugin install woocommerce --activate --allow-root 2>/dev/null || echo "WooCommerce already installed"

echo "=== Post-Install Complete ==="
echo "FA: http://${HOST_IP}:8080/install/"
echo "WP: http://${HOST_IP}:8081/"