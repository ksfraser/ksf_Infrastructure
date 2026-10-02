#!/bin/bash
# Rootless podman under user kevin. MANUAL FALLBACK ONLY.
#
# The authoritative way to create this container is:
#   ansible/roles/ksf.frontaccounting/tasks/container-run.yml
# via `cd ansible && ansible-playbook ksf-fa.yaml`. This script is kept
# because it is the verified 2026-09 rootless recipe and the rootless pod is
# currently driven by hand (the roles assume `become: true`, i.e. rootful).
# It mirrors the ansible mount set; if you change one, change both.
#
# WARNING: single-file binds only apply on a FRESH create, and any later
# write-then-rename to a bind target (ansible template/copy, sed -i, rsync
# without --inplace, git checkout) silently orphans the inode the container
# holds. Verify with ./fa-modules-doctor.sh --audit, or:
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
