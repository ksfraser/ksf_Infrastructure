#!/bin/bash
# KSF FA runtime permission fixer.
#
# FA writes to these paths at runtime (not baked into the image — they are
# bind-mounted per pod, or live in a named volume):
#   /var/www/html/installed_extensions.php  (global extension registry)
#   /var/www/html/config_db.php             (new companies)
#   /var/www/html/company/                  (new numbered company dirs + contents)
# The web worker runs as www-data (uid 33). Under rootless podman the host
# owner maps to root here, so www-data is "other" -> needs world write.
# Idempotent; safe to run every container start.
set -e

fix_writable() {
    for p in "$@"; do
        if [ -e "$p" ]; then
            chmod a+rwx "$p" 2>/dev/null || true
        fi
    done
}

fix_writable \
    /var/www/html/installed_extensions.php \
    /var/www/html/config_db.php

# Apache listen port.
#
# In pod mode every container shares ONE network namespace, so two containers
# cannot both bind :80. WordPress keeps 80/443 inside the pod, so FA takes
# FA_HTTP_PORT (8080). The pod publishes the host port to this container port
# (8090 -> 8080), so the URL people use (http://<host>:8090/) does not change.
# Outside a pod the default 80 is used and nothing changes.
if [ -n "${FA_HTTP_PORT:-}" ] && [ "${FA_HTTP_PORT}" != "80" ]; then
    sed -i "s/^Listen 80$/Listen ${FA_HTTP_PORT}/" /etc/apache2/ports.conf
    # FA serves plain HTTP only; 443 inside the pod belongs to WordPress.
    sed -i "s/^Listen 443$/# Listen 443 disabled by FA_HTTP_PORT/" /etc/apache2/ports.conf
fi

# company/ and everything under it (existing + future company dirs)
if [ -d /var/www/html/company ]; then
    chmod a+rwx /var/www/html/company 2>/dev/null || true
    find /var/www/html/company -mindepth 1 -maxdepth 2 -exec chmod a+rwx {} + 2>/dev/null || true
    find /var/www/html/company -type d \( -name pdf_files -o -name reporting \
        -o -name js_cache -o -name backup -o -name images -o -name attachments \) \
        -exec chmod a+rwx {} + 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"