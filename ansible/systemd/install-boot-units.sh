#!/usr/bin/env bash
# install-boot-units.sh - generate, install and enable the systemd units that
# bring the KSF FA stacks up after a reboot.
#
# WHAT IT INSTALLS
#
# 1. Rootful pod (ksfii_app) - one podman unit per container, generated from
#    the running containers so the unit matches the spec the ansible role
#    created (canonical mount set):
#        container-ksfii_app-mariadb.service
#        container-ksfii_app-fa.service            (FA, 8090)
#        container-ksfii_app-phpmyadmin.service    (8092)
#        container-ksfii_app-wordpress.service     (8091/8093)
#    Each unit runs `podman run --new ...`, i.e. it recreates the container at
#    boot from the stored spec. podman re-resolves every bind to the CURRENT
#    host inode on start, so a stopped container recovers with correct content
#    and no rebuild of the pod is needed.
#
# 2. Rootless pod (ksf_fa) - the same, but in kevin's USER systemd manager
#    (podman is rootless there):
#        container-ksf-mariadb.service
#        container-ksf-fa.service                  (FA, 8080)
#    Linger must be enabled for kevin, otherwise the user manager is not
#    running at boot and these units never fire:
#        loginctl enable-linger kevin
#
# 3. ksf-restore.service - a oneshot that runs the reconcile script after the
#    container units: it health-checks both stacks and rebuilds ONLY what is
#    actually broken (the ansible playbook is the rootful rebuild arm).
#
# 4. ksf-db-check.timer - weekly MariaDB consistency check, because an
#    unclean shutdown can leave corruption that InnoDB's redo-log replay does
#    not fix.
#
# Re-running this script is safe: it regenerates the units from the current
# containers and re-enables them.
set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
KSF_USER=${KSF_USER:-kevin}
KSF_UID=$(id -u "$KSF_USER")
ROOTFUL_UNITS=(
    container-ksfii_app-mariadb.service
    container-ksfii_app-fa.service
    container-ksfii_app-phpmyadmin.service
    container-ksfii_app-wordpress.service
)
ROOTLESS_UNITS=(
    container-ksf-mariadb.service
    container-ksf-fa.service
)

echo "==> enabling linger for $KSF_USER (rootless units need the user manager at boot)"
loginctl enable-linger "$KSF_USER" || true

echo "==> generating rootful podman units from the running containers"
cd "$HERE"
for c in ksfii_app-mariadb ksfii_app-fa ksfii_app-phpmyadmin ksfii_app-wordpress; do
    if podman container exists "$c"; then
        podman generate systemd --new --name "$c" --container-prefix container >"container-$c.service"
        echo "    container-$c.service"
    else
        echo "    SKIP $c - container does not exist (run ksf-restore.yaml first)" >&2
    fi
done

echo "==> generating rootless podman units (as $KSF_USER)"
for c in ksf-mariadb ksf-fa; do
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; cd '$HERE' && podman generate systemd --new --name '$c' --container-prefix container > 'container-$c.service'" || {
        echo "    SKIP $c - could not generate (container missing?)" >&2
        continue
    }
    echo "    container-$c.service"
done

# Explicit ordering: the database must be up before anything that talks to it.
add_dep() {
    local file=$1 dep=$2
    [[ -f $file ]] || return 0
    grep -q "^Requires=${dep}$" "$file" && return 0
    sed -i "s|^After=network-online.target$|After=network-online.target ${dep}\nRequires=${dep}|" "$file"
}

echo "==> adding dependencies"
add_dep "$HERE/container-ksfii_app-fa.service" container-ksfii_app-mariadb.service
add_dep "$HERE/container-ksfii_app-phpmyadmin.service" container-ksfii_app-mariadb.service
add_dep "$HERE/container-ksfii_app-wordpress.service" container-ksfii_app-mariadb.service
add_dep "$HERE/container-ksf-fa.service" container-ksf-mariadb.service

echo "==> installing rootful units to /etc/systemd/system"
for u in "${ROOTFUL_UNITS[@]}" ksf-restore.service ksf-db-check.service ksf-db-check.timer; do
    [[ -f "$HERE/$u" ]] || continue
    install -m 0644 "$HERE/$u" "/etc/systemd/system/$u"
    echo "    $u"
done

# The scripts live in the repo under /home/kevin/Documents, which SELinux
# labels user_home_t. systemd runs in init_t and is NOT allowed to execute
# files with that label ("Failed to locate executable ... Permission denied",
# AVC: denied { execute } ... tcontext=...user_home_t). So the units exec
# copies installed in /usr/local/sbin (bin_t), which is the real entry point;
# the repo copies stay the source of truth.
echo "==> installing scripts to /usr/local/sbin (SELinux: init_t cannot exec user_home_t)"
install -m 0755 "$HERE/ksf-boot-reconcile.sh" /usr/local/sbin/ksf-boot-reconcile.sh
install -m 0755 "$HERE/ksf-db-check.sh" /usr/local/sbin/ksf-db-check.sh
restorecon -F /usr/local/sbin/ksf-boot-reconcile.sh /usr/local/sbin/ksf-db-check.sh 2>/dev/null || true
echo "    /usr/local/sbin/ksf-boot-reconcile.sh"
echo "    /usr/local/sbin/ksf-db-check.sh"

echo "==> installing rootless units to ~$KSF_USER/.config/systemd/user"
install -d -m 0700 -o "$KSF_USER" -g "$(id -gn "$KSF_USER")" "/home/$KSF_USER/.config/systemd/user"
for u in "${ROOTLESS_UNITS[@]}"; do
    [[ -f "$HERE/$u" ]] || continue
    install -m 0644 -o "$KSF_USER" -g "$(id -gn "$KSF_USER")" "$HERE/$u" "/home/$KSF_USER/.config/systemd/user/$u"
    echo "    $u"
done

echo "==> daemon-reload"
systemctl daemon-reload
su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user daemon-reload"

echo "==> enabling units"
for u in "${ROOTFUL_UNITS[@]}"; do
    [[ -f "/etc/systemd/system/$u" ]] && systemctl enable "$u" && echo "    enabled $u"
done
systemctl enable ksf-restore.service ksf-db-check.timer && echo "    enabled ksf-restore.service ksf-db-check.timer"

for u in "${ROOTLESS_UNITS[@]}"; do
    [[ -f "/home/$KSF_USER/.config/systemd/user/$u" ]] || continue
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user enable '$u'" && echo "    enabled --user $u"
done

echo
echo "==> done. Current state:"
systemctl list-units 'container-*' 'ksf-*' --all --no-pager | grep -E "container-|ksf-" || true
su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user list-units 'container-*' --all --no-pager" || true
