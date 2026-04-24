#!/bin/bash
# KSF Post-Install Script
# Run after containers are up with: bash post-install.sh

set -e

echo "=== KSF Post-Install ==="

# Update WordPress to latest
echo "Updating WordPress..."
podman exec ksf-wp wp core update --allow-root || true

# Install WooCommerce if not already installed
echo "Installing WooCommerce..."
podman exec ksf-wp wp plugin install woocommerce --activate --allow-root 2>/dev/null || echo "WooCommerce already installed"

echo "=== Post-Install Complete ==="
echo "Now run FA install wizard at http://localhost:8080/install/"
echo "and WP admin at http://localhost:8081/wp-admin/"