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

### Option 1: Manual Podman Compose (Recommended for Development)

```bash
# Copy and customize environment
cp podman/.env.example podman/.env
nano podman/.env

# Copy all ksf_FA_* modules
cd /home/kevin/Documents
for dir in ksf_FA_*; do
  cp -r "$dir" ksf_Infrastructure/fa_modules/ 2>/dev/null || true
done

# Start all containers
cd podman
podman-compose up -d

# Check status
podman-compose ps
```

### Option 2: Ansible (Recommended for Production)

```bash
# Install Ansible
sudo apt install ansible

# Ensure modules are in place
ls /home/kevin/Documents/ksf_FA_*

# Run playbook
cd ansible
ansible-playbook -i localhost ksf-playbook.yaml --ask-become-pass
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

All ksf_FA_* modules are downladed via `ksf_fa_downloader` and available in FA Module Administration.

### How it Works
1. `ksf_fa_downloader` is pre-installed in the FA modules directory
2. On first run, go to **Setup > Download FA Modules**
3. All 25+ modules are listed with descriptions
4. Click **Download** next to any module you want
5. After downloading, go to **Setup > Module Administration** to activate

### Core Modules (Always Available)
- ksf_FA_ProjectManagement
- ksf_FA_HRM
- ksf_FA_Timesheets
- ksf_FA_TravelExpense
- ksf_FA_Training

### All Available Modules
| Module | Description |
|--------|-------------|
| ksf_FA_Assets | Equipment assets with depreciation |
| ksf_FA_Subscriptions | On-demand recurring billing |
| ksf_FA_Service | Field service and work orders |
| ksf_FA_KnowledgeBase | FAQ and knowledge base |
| ksf_FA_Fleet | Vehicle fleet with inspections |
| ksf_FA_TravelExpense | Travel and expense management |
| ksf_FA_HRM | Human resources management |
| ksf_FA_ProjectManagement | Project management |
| ksf_FA_Timesheets | Time tracking |
| ksf_FA_Leave | Leave management |
| ksf_FA_Onboarding | Employee onboarding |
| ksf_FA_Performance | Performance management |
| ksf_FA_Recruitment | Recruitment management |
| ksf_FA_Training | Training management |
| ksf_FA_OrgChart | Organization chart |
| ksf_FA_JobDescriptions | Job description management |
| ksf_FA_Teams | Team management |
| ksf_FA_Roster | Staff rostering |
| ksf_FA_Documents | Document management |
| ksf_FA_Forms | Dynamic form builder |
| ksf_FA_EmailManager | Email campaign management |
| ksf_FA_CampaignBuilder | Marketing campaign builder |
| ksf_FA_CRM | Customer relationship management |
| ksf_FA_Tracking | Link tracking |
| ksf_FA_Notes | Internal notes system |
| ksf_FA_WarrantyManagement | Warranty tracking |
| ksf_FA_Workflow | Workflow automation |
| ksf_FA_AsteriskPBX | Asterisk PBX with WebRTC |
| ksf_FA_API | REST API for FA |
| ksf_FA_Calendar | Calendar integration |
| **ksf_fa_downloader** | Module downloader (pre-installed) |
- ksf_FA_Assets
- ksf_FA_Subscriptions
- ksf_FA_Service
- ksf_FA_KnowledgeBase
- ksf_FA_Fleet
- ksf_FA_TravelExpense
- ksf_FA_HRM
- ksf_FA_Timesheets
- ksf_FA_Leave
- ksf_FA_Onboarding
- ksf_FA_Performance
- ksf_FA_Recruitment
- ksf_FA_Training
- ksf_FA_OrgChart
- ksf_FA_JobDescriptions
- ksf_FA_Teams
- ksf_FA_Roster
- ksf_FA_Documents
- ksf_FA_Forms
- ksf_FA_EmailManager
- ksf_FA_CampaignBuilder
- ksf_FA_CRM
- ksf_FA_Tracking
- ksf_FA_AsteriskPBX
- ksf_FA_API
- **ksf_FA_Downloader** (for easy module installation)

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