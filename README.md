# KSF Infrastructure for UAT

This directory contains the infrastructure configuration for deploying the KSF UAT (User Acceptance Testing) environment.

## Architecture Overview

```
ksf_Infrastructure/
├── ansible/                         # Ansible playbook + inventories
│   ├── ksf-playbook.yaml
│   └── inventories/
│       └── local                    # Local development inventory
├── podman/                          # Podman compose + config
│   ├── ksf-compose.yaml             # CANONICAL compose (FA uses ../FA/* binds, NOT named volume)
│   ├── start-fa.sh                  # Rootless ksf-fa podman run (verified 2026-09)
│   ├── post-install.sh
│   └── .env.example
├── init-sql/                        # DB initialization
│   └── init.sql
└── fa_modules/                      # FA modules (populated by playbook)
```

## IMPORTANT: Configuration via Inventory Files

**DO NOT hardcode values.** All configuration is done via Ansible inventory files.

### Inventory File Location
```
ansible/inventories/<environment>
```

### Required Inventory Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `fa_port` | FrontAccounting HTTP port | 8080 |
| `wp_port` | WordPress HTTP port | 8091 |
| `volume_prefix` | Prefix for podman volumes (must be unique per deployment) | ksf_infrastructure |
| `mariadb_root_pass` | MariaDB root password | ksfroot2024! |
| `mariadb_db` | MariaDB database name | ksf_fa |
| `fa_modules` | List of FA modules to deploy | [] (empty) |
| `stockmarket_worker_port` | Stock market Python worker HTTP port | 8000 |
| `enable_stockmarket_python_worker` | Enable the optional Python worker container | false |
| `stockmarket_python_dir` | Host path mounted into the Python worker container | /home/ksf_stockmarket/ksf_stockmarket/python |

### Example: Creating a New Environment

1. **Copy the local inventory:**
   ```bash
   cp ansible/inventories/local ansible/inventories/myenv
   ```

2. **Edit the inventory with your values:**
   ```ini
   # ansible/inventories/myenv
   [ksf:vars]
   fa_port=9000              # Changed port
   wp_port=9001             # Changed port
   volume_prefix=myenv_ksf   # UNIQUE prefix to avoid conflicts
   fa_modules:
     - ksf_FA_HRM
     - ksf_FA_ProjectManagement
     - export_woocommerce    # Your custom module
   ```

3. **Run the playbook:**
   ```bash
   ansible-playbook -i ansible/inventories/myenv ansible/ksf-playbook.yaml --ask-become-pass
   ```

## Quick Start (Using Ansible)

### 1. Install Ansible
```bash
sudo apt install ansible
```

### 2. Configure Inventory
Edit `ansible/inventories/local` to set:
- `fa_port` - FA HTTP port (avoid conflicts with other deployments)
- `wp_port` - WP HTTP port  
- `volume_prefix` - **MUST be unique** to avoid data collision
- `fa_modules` - List of modules to deploy

### 3. Run Playbook
```bash
cd ansible
ansible-playbook -i inventories/local ksf-playbook.yaml --ask-become-pass
```

## Manual Deployment (Podman)

FA (and Podman 4.x) is managed with **direct `podman run`**, not compose:
`podman-compose` (1.0.6 on this host) fails to parse the `${VAR:-default}`
top-level volume names in `ksf-compose.yaml` ("volume [...] not defined in top
level") — so `ksf-compose.yaml` is the **source of truth for the container
spec**, but the runnable path is `start-fa.sh`.

Copy `.env.example` to `.env` and set:

```bash
# podman/.env
FA_PORT=8080
WP_PORT=8091
VOLUME_PREFIX=my_unique_prefix   # REQUIRED - must be unique per deployment!
MARIADB_ROOT_PASSWORD=ksfroot2024!
MARIADB_DATABASE=ksf_fa
MARIADB_USER=ksf_user
MARIADB_PASSWORD=ksfuser2024!
```

Then start (rootless direct-run — the verified recipe; FA uses per-pod
`../FA/<pod>/*` binds, NOT a named data volume):

```bash
cd podman
bash start-fa.sh
```

After starting, ALWAYS verify the single-file binds actually applied (rootless
podman has been caught silently dropping them — see Troubleshooting):
```bash
sudo -n -u kevin podman exec ksf-fa grep -l config_db /proc/mounts
```

## Access (Default - Update Ports per Inventory)

| Service | URL | Default Credentials |
|---------|-----|-------------------|
| FrontAccounting | http://localhost:8080 | opencode / opencode |
| WordPress | http://localhost:8091 | admin / admin2024! |
| MariaDB | localhost:3306 | ksf_user / ksfuser2024! |
| Stock Market Python Worker | http://localhost:8000/health | Same host network (optional) |

## Volume Naming Convention

**CRITICAL:** Volumes are named `{volume_prefix}_mariadb_data`, `{volume_prefix}_wp_data`.

FA 2.4.3 no longer uses a named volume: the read-only FA source tree
(`../FA/2.4.3`) and writable per-pod files (`../FA/<pod>/config_db.php`,
`installed_extensions.php`, `company/`, `themes/default/default.css`) are
bind-mounted directly — see `ksf-compose.yaml`. This is what makes extension
activation writable on a fresh rootless create. Named volumes remain only for
MariaDB and WordPress.

Each deployment MUST have a unique `volume_prefix` to avoid:
- Data collision between environments
- Accidentally deleting another team's data
- Port conflicts

### Examples
| Environment | volume_prefix | Resulting Volumes |
|-------------|---------------|-------------------|
| Local Dev | `ksf_infrastructure` | ksf_infrastructure_mariadb_data, etc. |
| Staging | `ksf_staging` | ksf_staging_mariadb_data, etc. |
| Production | `ksf_prod` | ksf_prod_mariadb_data, etc. |

## Stopping and Cleanup

```bash
# Stop containers (keep data)
podman stop ksf-fa ksf-mariadb

# Remove the FA container (ready for a healthy recreate; data in MariaDB volume survives)
podman rm ksf-fa

# Remove volumes manually
podman volume rm ${VOLUME_PREFIX}_mariadb_data
podman volume rm ${VOLUME_PREFIX}_wp_data
```

## Troubleshooting

### Check container status
```bash
podman ps -a
```

### Check container logs
```bash
podman logs ksf-mariadb
podman logs ksf-fa
podman logs ksf-wp
```

### Verify volumes exist
```bash
podman volume ls | grep ${VOLUME_PREFIX}
```

### Common Issues

**Port already in use:**
```
Error: endpoint exposure failed: exposing port 8080-8091 failed
```
Solution: Update `fa_port`/`wp_port` in inventory to unused ports.

**Volume already exists:**
```
Error: volume some_name already exists
```
Solution: Either use a different `volume_prefix`, or manually remove:
```bash
podman volume rm <old_volume_name>
```

**"Cannot open the extension setup file 'installed_extensions.php' for writing"** on the Extensions page:
The ksf-fa container is serving the read-only git-tracked global registry
(`FA/2.4.3/installed_extensions.php`) instead of the per-pod writable one
(`FA/ksf_fa/installed_extensions.php`). Cause: rootless podman silently dropped
the single-file binds (see `start-fa.sh` header). Fix: recreate the container
with the correct `../FA/…` binds, then verify:
```bash
sudo -n -u kevin podman exec ksf-fa grep -l config_db /proc/mounts
```

**FA modules not appearing:**
1. Check `fa_modules` list in inventory
2. Verify modules exist in `/home/kevin/Documents/`
3. Check playbook output for "Skipped" messages