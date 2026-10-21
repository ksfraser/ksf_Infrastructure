#!/usr/bin/env bash
# install-rootless-pod.sh - (re)create the ROOTLESS ksf_fa pod and its
# containers, then refresh the user-level systemd units.
#
# The rootless stack runs podman as the unprivileged user "kevin", so it has
# its own container store, its own network and its own user systemd manager.
# It is the FA-only pod: MariaDB + FA (no WordPress overlay exists for it).
#
# MODEL: a real podman pod, like the rootful ksfii_app pod.
#   ksf_fa pod = ksf-mariadb + ksf-fa
#
# The containers share one network namespace, so only one of them may bind a
# given port. The database uses 3306, FA uses 8080 (WordPress keeps 80/443 in
# the rootful pod). The pod publishes the host port:
#   8080 -> 8080  (FA)
# The database is NOT published to the host - it is only reachable inside the
# pod, which is where FA looks for it (config_db.php: host ksf-mariadb).
#
# FA_HTTP_PORT=8080 is what makes the FA image listen on 8080 instead of 80
# (see containerfiles/FA/entrypoint.sh).
#
# After this script, run install-boot-units.sh to regenerate the user units
# from the containers this script created.
set -euo pipefail

KSF_USER=${KSF_USER:-kevin}
KSF_UID=$(id -u "$KSF_USER")
XDG=/run/user/$KSF_UID
POD=${POD:-ksf_fa}
NET=${NET:-ksf_network}
INFRA=/home/kevin/Documents/ksf_Infrastructure
FA_POD_DIR="$INFRA/FA/ksf_fa"

as_kevin() {
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=$XDG; $*"
}

echo "==> creating the rootless pod '$POD' (published 8080:8080)"
as_kevin "podman pod exists $POD || podman pod create --name $POD --network $NET -p 8080:8080"

echo "==> recreating the rootless containers as pod members"
# Park the user units first. They run `podman run --new`, so they recreate
# their container the moment it disappears - which would race the `podman run`
# below and fail with "the container name ... is already in use".
as_kevin "systemctl --user stop container-ksf-fa.service container-ksf-mariadb.service || true"
as_kevin "podman rm -f ksf-mariadb ksf-fa || true"

as_kevin "podman run -d --name ksf-mariadb --pod $POD \
  -e MYSQL_ROOT_PASSWORD=ksfroot2024! \
  -e MYSQL_USER=ksf_user \
  -e MYSQL_PASSWORD=ksfuser2024! \
  -e MYSQL_DATABASE=ksf_fa \
  -v ksf_infrastructure_mariadb_data:/var/lib/mysql \
  docker.io/library/mariadb:10.11"

as_kevin "podman run -d --name ksf-fa --pod $POD \
  --shm-size=64m \
  -e DB_DSN='mysql:host=ksf-mariadb;dbname=ksf_fa;charset=utf8' \
  -e DB_USER=ksf_user \
  -e DB_PASS='ksfuser2024!' \
  -e FA_HTTP_PORT=8080 \
  -v $INFRA/FA/2.4.3:/var/www/html:ro \
  -v $INFRA/fa_modules:/var/www/html/modules:rw \
  -v $FA_POD_DIR/config_db.php:/var/www/html/config_db.php:rw \
  -v $FA_POD_DIR/installed_extensions.php:/var/www/html/installed_extensions.php:rw \
  -v $FA_POD_DIR/company:/var/www/html/company:rw \
  -v $FA_POD_DIR/themes/default/default.css:/var/www/html/themes/default/default.css:rw \
  localhost/ksf-fa:php7.4 apache2-foreground"

sleep 8
echo "==> state:"
as_kevin "podman pod ps --format '{{.Name}} {{.Status}}'"
as_kevin "podman ps --format '{{.Names}} {{.Status}} {{.Ports}}'"
echo "==> FA HTTP check:"
curl -s -o /dev/null -w "    8080 -> %{http_code}\n" --max-time 10 http://127.0.0.1:8080/
