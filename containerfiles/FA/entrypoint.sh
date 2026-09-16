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

# company/ and everything under it (existing + future company dirs)
if [ -d /var/www/html/company ]; then
    chmod a+rwx /var/www/html/company 2>/dev/null || true
    find /var/www/html/company -mindepth 1 -maxdepth 2 -exec chmod a+rwx {} + 2>/dev/null || true
    find /var/www/html/company -type d \( -name pdf_files -o -name reporting \
        -o -name js_cache -o -name backup -o -name images -o -name attachments \) \
        -exec chmod a+rwx {} + 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"