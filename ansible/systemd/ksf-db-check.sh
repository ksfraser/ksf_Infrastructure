#!/usr/bin/env bash
# ksf-db-check.sh - MariaDB table consistency check for the KSF stacks.
#
# After an unclean shutdown (power loss) InnoDB replays its redo log on the
# next start, which recovers almost everything. What it cannot repair is
# silent index/table corruption in older engines (MyISAM/Aria), which shows up
# later as failing reports or "table is marked as crashed". This script runs
# mariadb-check weekly and logs anything it finds, so the damage is visible
# before a user hits it.
#
# It is CHECK-ONLY on purpose. mariadb-check --repair rewrites table data
# structures; running that unattended against a live accounting database is
# not a risk worth taking. When this script reports errors, the repair is a
# deliberate, supervised step (see the operator notes in AGENTS_APPENDIX.md).
set -uo pipefail

LOG_FILE=${LOG_FILE:-/var/log/ksf-db-check.log}
KSF_USER=${KSF_USER:-kevin}

log() {
    local msg="[$(date -Is)] $*"
    echo "$msg"
    logger -t ksf-db-check -- "$*"
    [[ -w "$(dirname "$LOG_FILE")" ]] && echo "$msg" >>"$LOG_FILE"
    return 0
}

check_container() {
    local ctr=$1 pw_var=$2 user=$3
    log "=== checking $ctr ==="
    podman exec "$ctr" sh -c "
        set -e
        pw=\$$pw_var
        mariadb-check --user=root --password=\"\$pw\" --all-databases --check 2>&1 || \
        mysqlcheck --user=root --password=\"\$pw\" --all-databases --check 2>&1
    " 2>&1 | while read -r line; do log "  $ctr: $line"; done
}

check_container ksfii_app-mariadb MARIADB_ROOT_PASSWORD root

su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/\$(id -u $KSF_USER); podman exec ksf-mariadb sh -c 'set -e; pw=\$MYSQL_ROOT_PASSWORD; mariadb-check --user=root --password=\"\$pw\" --all-databases --check 2>&1 || mysqlcheck --user=root --password=\"\$pw\" --all-databases --check 2>&1'" 2>&1 \
    | while read -r line; do log "  ksf-mariadb (rootless): $line"; done

log "=== db check done ==="
exit 0
