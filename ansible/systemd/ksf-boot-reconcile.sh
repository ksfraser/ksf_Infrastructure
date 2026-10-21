#!/usr/bin/env bash
# ksf-boot-reconcile.sh - post-boot health check and CONDITIONAL repair for the
# KSF FrontAccounting stacks.
#
# WHY THIS EXISTS
# After a hard reboot / power loss the podman containers come back stopped (or
# in "Created" state) and the databases may need crash recovery. The naive
# response is to rebuild everything - which works, but it is slow, it churns
# container identity, and it hides the actual fault.
#
# The reality (verified): a rebuild is almost never required. `podman start`
# re-resolves every bind to the CURRENT host inode, so a stopped container
# recovers with the right content on its own. MariaDB/InnoDB also replays its
# redo log on startup. The historical "must rebuild" incidents were all
# provisioning drift - stale systemd units pointing at paths that no longer
# existed (lowercase fa/, ksff_fa/company/0), containers hand-made outside the
# ansible roles, and a bind-inode check that reported every bind as dead.
#
# So this script asks each stack whether it is actually serving, and repairs
# ONLY what is broken:
#
#   rootful pod  (ksfii_app, FA on 8090) -> systemctl restart, then the
#                                            ansible playbook (the rebuild arm)
#   rootless pod (ksf_fa,  FA on 8080)  -> systemctl --user restart (the
#                                            rootless container spec is owned
#                                            by the user-level systemd units)
#
# A healthy stack is never touched.
set -uo pipefail

ROOTFUL_FA_URL=${ROOTFUL_FA_URL:-http://127.0.0.1:8090/}
ROOTLESS_FA_URL=${ROOTLESS_FA_URL:-http://127.0.0.1:8080/}
ROOTFUL_MARIADB=${ROOTFUL_MARIADB:-ksfii_app-mariadb}
ROOTLESS_MARIADB=${ROOTLESS_MARIADB:-ksf-mariadb}
KSF_USER=${KSF_USER:-kevin}
ANSIBLE_DIR=${ANSIBLE_DIR:-/home/kevin/Documents/ksf_Infrastructure/ansible}
VAULT_PASS=${VAULT_PASS:-/root/.ansible/.vaultpass}
LOG_FILE=${LOG_FILE:-/var/log/ksf-reconcile.log}
# The systemd units that own the rootful pod's containers. Two managers for the
# same container fight: the playbook does `podman rm -f` + `podman run`, while
# a running --new unit recreates the container the moment it disappears, so
# the playbook's `podman run` fails with "name already in use". The rebuild arm
# therefore parks the units first and hands control back afterwards.
# The pod unit is included: the containers cannot be recreated without it.
ROOTFUL_UNITS=${ROOTFUL_UNITS:-"pod-ksfii_app.service container-ksfii_app-mariadb.service container-ksfii_app-fa.service container-ksfii_app-phpmyadmin.service container-ksfii_app-wordpress.service"}

log() {
    local msg="[$(date -Is)] $*"
    echo "$msg"
    logger -t ksf-reconcile -- "$*"
    [[ -w "$(dirname "$LOG_FILE")" ]] && echo "$msg" >>"$LOG_FILE"
    return 0
}

# Is the URL serving? FA answers 200 on the login page; a broken DB shows up
# as a 500, so this also covers "mariadb is up but the schema is unhappy".
http_ok() {
    curl -fsS -o /dev/null --max-time 10 "$1" 2>/dev/null
}

# Poll a URL for up to $2 seconds (default 60).
wait_for() {
    local url=$1 tries=${2:-30} i=0
    while ((i < tries)); do
        if http_ok "$url"; then return 0; fi
        sleep 2
        ((i++))
    done
    return 1
}

# MariaDB sanity: is it answering, and does it still have the FA tables?
# The container carries its own root password in MARIADB_ROOT_PASSWORD, so no
# secret has to be passed on the command line.
mariadb_report() {
    local ctr=$1
    if ! podman exec "$ctr" sh -c 'mysqladmin ping -uroot -p"$MARIADB_ROOT_PASSWORD" --silent' >/dev/null 2>&1; then
        log "MARIA DB UNREACHABLE: $ctr (mysqladmin ping failed)"
        podman logs --tail 20 "$ctr" 2>&1 | grep -iE "crash|recovery|corrupt|error" | tail -5 | while read -r l; do log "  $ctr: $l"; done
        return 1
    fi
    local tables
    tables=$(podman exec "$ctr" sh -c 'mysql -uroot -p"$MARIADB_ROOT_PASSWORD" -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"ksf_fa\""' 2>/dev/null | tr -d '\r')
    log "MARIA DB OK: $ctr tables=${tables:-unknown}"
    return 0
}

log "=== KSF reconcile start ==="

# The pods own the network namespace and the published ports. A pod that is
# not running takes every service in it down, so check the pods first.
pod_report() {
    local pod=$1
    if podman pod exists "$pod"; then
        local state
        state=$(podman pod ps --format '{{.Status}}' --filter "name=$pod" 2>/dev/null | head -1)
        log "POD $pod: ${state:-unknown}"
    else
        log "POD $pod: MISSING"
    fi
}
pod_report ksfii_app
su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$(id -u $KSF_USER); podman pod ps --format '{{.Name}} {{.Status}}'" 2>&1 |
    while read -r name state; do
        log "POD $name: $state (rootless)"
    done

# ---------------------------------------------------------------- rootful pod
if wait_for "$ROOTFUL_FA_URL" 30; then
    log "rootful FA healthy at $ROOTFUL_FA_URL"
else
    log "rootful FA NOT serving at $ROOTFUL_FA_URL - restarting its systemd unit"
    systemctl restart container-ksfii_app-fa.service >/dev/null 2>&1
    if wait_for "$ROOTFUL_FA_URL" 30; then
        log "rootful FA recovered by container restart"
    else
        log "rootful FA still down - running the ansible rebuild (ksf-restore.yaml)"
        # Park the systemd units so they cannot recreate a container behind the
        # playbook's back (see ROOTFUL_UNITS above).
        for u in $ROOTFUL_UNITS; do
            systemctl stop "$u" >/dev/null 2>&1
        done
        log "  parked units: $ROOTFUL_UNITS"
        if cd "$ANSIBLE_DIR" && ansible-playbook -i inventories/local ksf-restore.yaml \
                --vault-password-file "$VAULT_PASS" -e pod_name=ksfii_app >/var/log/ksf-restore.log 2>&1; then
            wait_for "$ROOTFUL_FA_URL" 45 && log "rootful FA recovered by rebuild" \
                || log "rootful FA STILL down after rebuild - see /var/log/ksf-restore.log"
        else
            log "rootful FA rebuild FAILED - see /var/log/ksf-restore.log"
        fi
        # Hand the containers back to systemd so it owns them again (and can
        # restart them if they die). The units recreate the containers from the
        # same canonical spec the playbook just used.
        for u in $ROOTFUL_UNITS; do
            systemctl start "$u" >/dev/null 2>&1
        done
        wait_for "$ROOTFUL_FA_URL" 45 && log "rootful FA up under systemd control" \
            || log "rootful FA not answering after handing back to systemd"
    fi
fi
mariadb_report "$ROOTFUL_MARIADB"

# --------------------------------------------------------------- rootless pod
# The rootless pod runs as an unprivileged user, so it is driven through that
# user's systemd manager. Linger is enabled, so the manager is up at boot.
rootless_start() {
    # The pod unit comes first: the container units require it (the pod owns
    # the network namespace and the published port).
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$(id -u $KSF_USER); systemctl --user start pod-ksf_fa.service container-ksf-fa.service" >/dev/null 2>&1
}
rootless_mariadb_report() {
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$(id -u $KSF_USER); podman exec $ROOTLESS_MARIADB sh -c 'mysqladmin ping -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --silent'" >/dev/null 2>&1 \
        && log "MARIA DB OK: $ROOTLESS_MARIADB (rootless)" \
        || log "MARIA DB UNREACHABLE: $ROOTLESS_MARIADB (rootless)"
}

if wait_for "$ROOTLESS_FA_URL" 30; then
    log "rootless FA healthy at $ROOTLESS_FA_URL"
else
    log "rootless FA NOT serving at $ROOTLESS_FA_URL - restarting its user unit"
    rootless_start
    if wait_for "$ROOTLESS_FA_URL" 30; then
        log "rootless FA recovered by container restart"
    else
        log "rootless FA still down - the rootless pod has no ansible model; inspect manually"
        su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$(id -u $KSF_USER); podman logs --tail 20 ksf-fa" 2>&1 | tail -20 | while read -r l; do log "  ksf-fa: $l"; done
    fi
fi
rootless_mariadb_report

log "=== KSF reconcile done ==="
exit 0
