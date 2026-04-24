# KSF Infrastructure for UAT

This directory contains the infrastructure configuration for deploying the KSF UAT (User Acceptance Testing) environment.

## Components

```
ksf_Infrastructure/
├── ansible/                    # Ansible playbook
│   └── ksf_playbook.yaml
├── podman/                     # Podman compose + config
│   ├── ksf-compose.yaml
│   └── .env.example
├── containerfiles/              # Container configurations
│   ├── FA/php.ini
│   └── WP/uploads.ini
└── init-sql/                   # DB initialization
    └── init.sql
```

## Quick Start

### Option 1: Manual Podman Compose

```bash
# Copy and customize environment
cp podman/.env.example podman/.env
nano podman/.env

# Start all containers
cd podman
podman-compose up -d

# Check status
podman-compose ps
```

### Option 2: Ansible (Recommended)

```bash
# Install Ansible
sudo apt install ansible

# Run playbook
cd ansible
ansible-playbook -i localhost ksf_playbook.yaml --ask-become-pass
```

## Access

| Service | URL | Default Credentials |
|---------|-----|-------------------|
| FrontAccounting | http://localhost:8080 | admin / admin |
| WordPress | http://localhost:8081 | admin / admin2024! |
| MariaDB | localhost:3306 | ksf_user / ksfuser2024! |

## Environment Variables

Edit `podman/.env`:

```
MARIADB_ROOT_PASSWORD=ksfroot2024!
MARIADB_DATABASE=ksf_fa
MARIADB_USER=ksf_user
MARIADB_PASSWORD=ksfuser2024!
FA_ADMIN_PASSWORD=admin
WP_ADMIN_PASSWORD=admin2024!
```

## Container Ports

| Container | Port | Internal |
|----------|------|----------|
| MariaDB | 3306 | 3306 |
| FrontAccounting | 8080 | 80 |
| WordPress | 8081 | 80 |

## Data Persistence

Data is stored in Podman volumes:
- `mariadb_data` - MySQL/MariaDB data
- `fa_data` - FrontAccounting files  
- `wp_data` - WordPress files

## Stop

```bash
cd podman
podman-compose down     # Keep volumes
podman-compose down -v  # Destroy volumes
```

## Modules Auto-Installed

- ksf_FA_ProjectManagement
- ksf_FA_Calendar
- ksf_FA_SupportTickets
- ksf_FA_WarrantyManagement
- ksf_FA_Notes
- ksf_FA_Workflow

## Troubleshooting

### Check container logs
```bash
podman logs ksf-mariadb
podman logs ksf-fa
podman logs ksf-wp
```

### Reset everything
```bash
podman-compose down -v
podman volume rm ksf_infrastructure_mariadb_data
podman volume rm ksf_infrastructure_fa_data
podman volume rm ksf_infrastructure_wp_data
podman-compose up -d
```