#!/usr/bin/env bash
# install-boot-units.sh - generate, install and enable the systemd units that
# bring the KSF FA stacks up after a reboot.
#
# WHAT IT INSTALLS
#
# 1. Pod unit (pod-<pod>.service) - one per podman pod. It is written by hand
#    rather than generated, because `podman generate systemd` names the unit
#    after the pod's infra container ID, which changes every time the pod is
#    recreated. The hand-written unit is name-based and idempotent: it creates
#    the pod with the published port map if it is missing, then starts it.
#
# 2. Container units (container-<name>.service) - one per container, generated
#    from the RUNNING container with `podman generate systemd --new`, so the
#    unit carries exactly the spec the ansible role created (canonical mount
#    set, and --pod when the container is a pod member). Each unit recreates
#    its container at boot; `podman run --pod X` also starts the pod's infra
#    container if the pod is stopped.
#
# 3. ksf-restore.service - a oneshot that runs the reconcile script after the
#    container units: it health-checks both stacks and rebuilds ONLY what is
#    actually broken (the ansible playbook is the rootful rebuild arm).
#
# 4. ksf-db-check.timer - weekly MariaDB consistency check, because an
#    unclean shutdown can leave corruption that InnoDB's redo-log replay does
#    not fix.
#
# RUN IT AFTER EVERY ANSIBLE PLAYBOOK RUN. The units are generated from the
# live containers; if the playbook changed a container's spec (pod membership,
# ports, mounts) and the units are not regenerated, the stale unit recreates
# the container with the OLD spec at the next boot - and, worse, a stale unit
# that is still ACTIVE will fight the playbook (it recreates the container the
# moment the playbook removes it, so the playbook's `podman run` fails with
# "the container name ... is already in use").
#
# Re-running this script is safe: it regenerates the units from the current
# containers and re-enables them.
set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
KSF_USER=${KSF_USER:-kevin}
KSF_UID=$(id -u "$KSF_USER")
ROOTFUL_POD=${ROOTFUL_POD:-ksfii_app}
ROOTLESS_POD=${ROOTLESS_POD:-ksf_fa}
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

# ---------------------------------------------------------------- pod unit
# The pod publishes the host ports for the whole pod. Read the live map from
# the pod's infra container so the unit cannot drift from reality.
echo "==> generating the pod unit for '$ROOTFUL_POD'"
if podman pod exists "$ROOTFUL_POD"; then
    infra_id=$(podman pod inspect "$ROOTFUL_POD" --format '{{.InfraContainerID}}' | tr -d '[:space:]')
    port_map=$(podman inspect "$infra_id" \
        --format '{{range $k, $v := .HostConfig.PortBindings}}{{range $v}}{{.HostPort}}:{{$k}};{{end}}{{end}}' \
        | tr -d '[:space:]' | sed 's|/tcp||g')
    port_args=""
    IFS=';' read -ra parts <<<"$port_map"
    for p in "${parts[@]}"; do
        [[ -n "$p" ]] && port_args+=" -p $p"
    done
    cat >"$HERE/pod-$ROOTFUL_POD.service" <<EOF
# pod-$ROOTFUL_POD.service
# The pod owns the network namespace, the published ports and the lifecycle of
# every container in it (FA + MariaDB + WordPress).
#
# Written by hand rather than by 'podman generate systemd': that names the
# unit after the pod's infra container ID, which changes whenever the pod is
# recreated. This unit is name-based and idempotent - it creates the pod with
# the published port map if it is missing, then starts it.
#
# The port map below is read from the live pod by install-boot-units.sh.

[Unit]
Description=Podman pod $ROOTFUL_POD (FA + MariaDB + WordPress)
Documentation=man:podman-pod-create(1)
Wants=network-online.target
After=network-online.target
RequiresMountsFor=%t/containers

[Service]
Environment=PODMAN_SYSTEMD_UNIT=%n
Restart=on-failure
TimeoutStopSec=70
ExecStart=/bin/sh -c '/usr/bin/podman pod exists $ROOTFUL_POD || /usr/bin/podman pod create --name $ROOTFUL_POD --network $ROOTFUL_POD$port_args; /usr/bin/podman pod start $ROOTFUL_POD'
ExecStop=/usr/bin/podman pod stop -t 10 $ROOTFUL_POD
Type=oneshot
RemainAfterExit=yes

[Install]
WantedBy=default.target
EOF
    echo "    pod-$ROOTFUL_POD.service ($port_map)"
else
    echo "    SKIP - pod '$ROOTFUL_POD' does not exist (run ksf-restore.yaml first)" >&2
fi

# ---------------------------------------------------------- container units
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
# Generate into a directory the unprivileged user can write to, then move the
# result next to the rootful units.
ROOTLESS_TMP=$(mktemp -d)
chmod 0777 "$ROOTLESS_TMP"
for c in ksf-mariadb ksf-fa; do
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; cd '$ROOTLESS_TMP' && podman generate systemd --new --name '$c' --container-prefix container > 'container-$c.service'" || {
        echo "    SKIP $c - could not generate (container missing?)" >&2
        continue
    }
    mv -f "$ROOTLESS_TMP/container-$c.service" "$HERE/container-$c.service"
    echo "    container-$c.service"
done
rm -rf "$ROOTLESS_TMP"

# Explicit ordering: the pod (and the database) must be up before anything
# that talks to them.
add_dep() {
    local file=$1 dep=$2
    [[ -f $file ]] || return 0
    grep -q "^Requires=${dep}$" "$file" && return 0
    sed -i "s|^After=network-online.target$|After=network-online.target ${dep}\nRequires=${dep}|" "$file"
}

echo "==> adding dependencies"
for u in "${ROOTFUL_UNITS[@]}"; do
    add_dep "$HERE/$u" "pod-$ROOTFUL_POD.service"
done
add_dep "$HERE/container-ksf-fa.service" container-ksf-mariadb.service
add_dep "$HERE/container-ksf-mariadb.service" "pod-$ROOTLESS_POD.service"
add_dep "$HERE/container-ksf-fa.service" "pod-$ROOTLESS_POD.service"

echo "==> installing rootful units to /etc/systemd/system"
for u in "${ROOTFUL_UNITS[@]}" "pod-$ROOTFUL_POD.service" ksf-restore.service ksf-db-check.service ksf-db-check.timer; do
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

# The rootless pod gets the same name-based, idempotent unit as the rootful
# one (see above), in the USER systemd manager, because the rootless podman
# store belongs to $KSF_USER.
echo "==> generating the rootless pod unit for '$ROOTLESS_POD'"
if su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; podman pod exists $ROOTLESS_POD"; then
    rootless_ports=$(su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; infra=\$(podman pod inspect $ROOTLESS_POD --format '{{.InfraContainerID}}' | tr -d '[:space:]'); podman inspect \$infra --format '{{range \$k, \$v := .HostConfig.PortBindings}}{{range \$v}}{{\.HostPort}}:{{\$k}};{{end}}{{end}}' | tr -d '[:space:]' | sed 's|/tcp||g'")
    port_args=""
    IFS=';' read -ra parts <<<"$rootless_ports"
    for p in "${parts[@]}"; do
        [[ -n "$p" ]] && port_args+=" -p $p"
    done
    cat >"$HERE/pod-$ROOTLESS_POD.service" <<EOF
# pod-$ROOTLESS_POD.service (rootless)
# The rootless pod owns the network namespace, the published ports and the
# lifecycle of its containers (FA + MariaDB). Name-based and idempotent, so a
# recreated pod does not invalidate the unit.

[Unit]
Description=Podman pod $ROOTLESS_POD (FA + MariaDB, rootless)
Documentation=man:podman-pod-create(1)
Wants=network-online.target
After=network-online.target
RequiresMountsFor=%t/containers

[Service]
Environment=PODMAN_SYSTEMD_UNIT=%n
Restart=on-failure
TimeoutStopSec=70
ExecStart=/bin/sh -c '/usr/bin/podman pod exists $ROOTLESS_POD || /usr/bin/podman pod create --name $ROOTLESS_POD --network ksf_network$port_args; /usr/bin/podman pod start $ROOTLESS_POD'
ExecStop=/usr/bin/podman pod stop -t 10 $ROOTLESS_POD
Type=oneshot
RemainAfterExit=yes

[Install]
WantedBy=default.target
EOF
    install -m 0644 -o "$KSF_USER" -g "$(id -gn "$KSF_USER")" "$HERE/pod-$ROOTLESS_POD.service" "/home/$KSF_USER/.config/systemd/user/pod-$ROOTLESS_POD.service"
    echo "    pod-$ROOTLESS_POD.service ($rootless_ports)"
else
    echo "    SKIP - pod '$ROOTLESS_POD' does not exist (run install-rootless-pod.sh first)" >&2
fi

for u in "${ROOTLESS_UNITS[@]}"; do
    [[ -f "$HERE/$u" ]] || continue
    install -m 0644 -o "$KSF_USER" -g "$(id -gn "$KSF_USER")" "$HERE/$u" "/home/$KSF_USER/.config/systemd/user/$u"
    echo "    $u"
done

echo "==> daemon-reload"
systemctl daemon-reload
su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user daemon-reload"

echo "==> enabling units"
systemctl enable "pod-$ROOTFUL_POD.service" 2>/dev/null || true
for u in "${ROOTFUL_UNITS[@]}"; do
    [[ -f "/etc/systemd/system/$u" ]] && systemctl enable "$u" && echo "    enabled $u"
done
systemctl enable ksf-restore.service ksf-db-check.timer && echo "    enabled ksf-restore.service ksf-db-check.timer"

for u in "${ROOTLESS_UNITS[@]}" "pod-$ROOTLESS_POD.service"; do
    [[ -f "/home/$KSF_USER/.config/systemd/user/$u" ]] || continue
    su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user enable '$u'" && echo "    enabled --user $u"
done

echo
echo "==> done. Current state:"
systemctl list-units 'container-*' 'pod-*' 'ksf-*' --all --no-pager | grep -E "container-|pod-|ksf-" || true
su - "$KSF_USER" -c "export XDG_RUNTIME_DIR=/run/user/$KSF_UID; systemctl --user list-units 'container-*' --all --no-pager" || true
