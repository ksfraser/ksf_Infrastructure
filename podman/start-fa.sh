#!/bin/bash
# Rootless podman under user kevin (NOT root, NOT compose). Same spec as
# ksf-compose.yaml's frontaccounting service (verified 2026-09 rootless).
# WARNING: single-file binds only apply on a FRESH create - verify with:
#   sudo -n -u kevin podman exec ksf-fa grep config_db /proc/mounts
podman run -d \
  --name ksf-fa \
  --hostname ksf-fa \
  --network ksf_network \
  -p 8080:80 \
  -e DB_DSN="mysql:host=ksf-mariadb;dbname=ksf_fa;charset=utf8" \
  -e DB_USER=ksf_user \
  -e DB_PASS=ksfuser2024! \
  -v ../FA/2.4.3:/var/www/html:ro \
  -v ../fa_modules:/var/www/html/modules:rw \
  -v ../FA/ksf_fa/config_db.php:/var/www/html/config_db.php:rw \
  -v ../FA/ksf_fa/installed_extensions.php:/var/www/html/installed_extensions.php:rw \
  -v ../FA/ksf_fa/company:/var/www/html/company:rw \
  -v ../FA/ksf_fa/themes/default/default.css:/var/www/html/themes/default/default.css:rw \
  localhost/ksf-fa:php7.4 \
  apache2-foreground
