# Business Requirements - ksf_Infrastructure

## Document Information
- **Module**: ksf_Infrastructure
- **Version**: 1.0.0
- **Date**: 2026-05-13
- **Status**: Implemented
- **Author**: KSFII Development Team

---

## 1. Project Overview

### 1.1 Purpose
The ksf_Infrastructure module provides containerized deployment configuration for the KSF ecosystem, enabling consistent development, testing, and production environments using Podman containers and Ansible automation.

### 1.2 Business Problem Statement
Organizations need reliable, repeatable deployment processes. ksf_Infrastructure provides:
- Containerized FrontAccounting deployment
- WordPress integration for web content
- MariaDB database setup
- Automated provisioning via Ansible
- Consistent environments across dev/staging/production

### 1.3 Scope

| Component | Included |
|-----------|----------|
| Podman Containers | Yes |
| Docker Compose Alternative | Yes |
| Ansible Playbooks | Yes |
| Database Initialization | Yes |
| FrontAccounting Modules | Yes |
| WordPress Integration | Yes |
| Optional Python Worker | Yes |
| SSL/TLS | No (Future) |
| Load Balancing | No (Future) |

---

## 2. Module Architecture

### 2.1 Directory Structure

```
ksf_Infrastructure/
├── ansible/                    # Ansible automation
│   └── ksf_playbook.yaml
├── containerfiles/             # Container configs
│   ├── FA/php.ini
│   ├── python/Podfile         # Optional FastAPI Python worker
│   └── WP/uploads.ini
├── fa_modules/                 # FA module storage
├── init-sql/                   # Database setup
│   └── init.sql
├── modules/                    # FA reporting modules
│   └── rep_statement_reconcile/
├── podman/                     # Podman compose
│   ├── ksf-compose.yaml
│   └── .env.example
└── README.md
```

### 2.2 Core Components

#### Podman Containers

| Container | Image | Purpose | Ports |
|----------|-------|---------|-------|
| ksf-mariadb | mariadb:latest | Database | 3306 |
| ksf-fa | custom (FA) | FrontAccounting | 8080 |
| ksf-wp | wordpress:latest | WordPress | 8081 |

#### Ansible Playbooks

| Playbook | Purpose |
|----------|---------|
| ksf_playbook.yaml | Full stack deployment |
| ksf_fa_downloader | Module download automation |

---

## 3. Functional Features

### 3.1 Container Management

| Feature | Description |
|---------|-------------|
| Start All | podman-compose up -d |
| Stop All | podman-compose down |
| View Logs | podman logs <container> |
| Reset | podman-compose down -v |

### 3.2 Database Management

| Feature | Description |
|---------|-------------|
| Auto-init | SQL scripts run on first start |
| Data Persistence | MariaDB volume |
| Backup | Manual via docker/mariadb commands |

### 3.3 Module Distribution

| Feature | Description |
|---------|-------------|
| ksf_fa_downloader | Pre-installed module manager |
| Module Download | On-demand module installation |
| Module Activation | Via FA Module Administration |

### 3.4 Environment Configuration

| Variable | Description | Default |
|----------|-------------|---------|
| MARIADB_ROOT_PASSWORD | DB root password | ksfroot2024! |
| MARIADB_DATABASE | Initial database | ksf_fa |
| MARIADB_USER | Application user | ksf_user |
| MARIADB_PASSWORD | User password | ksfuser2024! |
| FA_ADMIN_PASSWORD | FA admin password | admin |
| WP_ADMIN_PASSWORD | WordPress admin password | admin2024! |

---

## 4. Integration Dependencies

### 4.1 System Requirements

| Requirement | Version |
|-------------|---------|
| Podman | 3.0+ |
| Podman Compose | Latest |
| Ansible | 2.9+ |
| Linux | Ubuntu 20.04+ |

### 4.2 Platform Dependencies

| Platform | Version | Purpose |
|----------|---------|---------|
| FrontAccounting | 2.4.x | Core ERP |
| WordPress | Latest | CMS integration |
| MariaDB | 10.5+ | Database |

### 4.3 Module Dependencies

| Module | Purpose |
|--------|---------|
| ksf_FA_* | All FA modules |
| ksf_fa_downloader | Module management |

---

## 5. Data Flow

### 5.1 Deployment Flow

```
User runs: podman-compose up -d
    ↓
Podman pulls images
    ↓
MariaDB container starts
    ↓
Init SQL runs
    ↓
FA container starts
    ↓
WP container starts
    ↓
All services ready
```

### 5.2 Module Installation Flow

```
User visits FA > Setup > Download Modules
    ↓
ksf_fa_downloader lists available modules
    ↓
User selects module
    ↓
Module downloaded to fa_modules/
    ↓
User activates via Module Administration
    ↓
Module available in FA
```

---

## 6. Network Architecture

### 6.1 Container Network

```
┌─────────────────────────────────────────┐
│           ksf_infrastructure            │
│              (Network)                   │
├─────────────────────────────────────────┤
│                                         │
│  ┌─────────────┐    ┌─────────────┐    │
│  │  ksf-mariadb│◄───│   ksf-fa    │    │
│  │   Port:3306 │    │   Port:8080 │    │
│  └─────────────┘    └──────┬──────┘    │
│                            │            │
│                            │            │
│                     ┌──────┴──────┐     │
│                     │   ksf-wp    │      │
│                     │  Port:8081  │      │
│                     └─────────────┘     │
│                                         │
└─────────────────────────────────────────┘
```

### 6.2 External Access

| Service | URL | Purpose |
|---------|-----|---------|
| FrontAccounting | http://localhost:8080 | ERP access |
| WordPress | http://localhost:8081 | CMS access |
| MariaDB | localhost:3306 | Database |

---

## 7. Volume Mounts

| Container | Volume | Host Path |
|-----------|--------|-----------|
| ksf-mariadb | mariadb_data | Managed by Podman |
| ksf-fa | fa_data | ./fa_data |
| ksf-wp | wp_data | ./wp_data |

---

## 8. Security Configuration

### 8.1 Default Credentials

| Service | Username | Password |
|---------|----------|----------|
| FA Admin | admin | admin |
| WP Admin | admin | admin2024! |
| MariaDB Root | root | ksfroot2024! |
| MariaDB User | ksf_user | ksfuser2024! |

**Warning**: Change all default passwords before production deployment.

### 8.2 Network Security
- Containers isolated on internal network
- Only exposed ports accessible externally
- Database not exposed to external network

---

## 9. Deployment Options

### 9.1 Option 1: Podman Compose (Development)

```bash
cd podman
podman-compose up -d
```

**Pros**: Quick setup, good for development
**Cons**: Manual container management

### 9.2 Option 2: Ansible (Production)

```bash
cd ansible
ansible-playbook -i localhost ksf_playbook.yaml --ask-become-pass
```

**Pros**: Full automation, reproducible
**Cons**: Requires Ansible knowledge

---

## 10. Maintenance

### 10.1 Backup

```bash
# Backup MariaDB
podman exec ksf-mariadb mysqldump -u root -p ksf_fa > backup.sql

# Backup volumes
podman volume ls
podman volume inspect ksf_infrastructure_mariadb_data
```

### 10.2 Updates

```bash
# Pull latest images
podman pull mariadb:latest
podman pull wordpress:latest

# Recreate containers
podman-compose down
podman-compose up -d
```

### 10.3 Monitoring

```bash
# Check container status
podman-compose ps

# View logs
podman logs -f ksf-mariadb
podman logs -f ksf-fa
podman logs -f ksf-wp
```

---

## 11. Troubleshooting

### 11.1 Common Issues

| Issue | Solution |
|-------|----------|
| Container won't start | Check logs: podman logs <container> |
| Database connection failed | Verify MARIADB_* env vars |
| Module download fails | Check network connectivity |
| Port already in use | Change port mapping in compose |

### 11.2 Reset Environment

```bash
podman-compose down -v
podman volume rm ksf_infrastructure_mariadb_data
podman-compose up -d
```

---

## 12. Future Enhancements

| Feature | Priority | Description |
|---------|----------|-------------|
| Docker support | High | Add Docker Compose alternative |
| SSL/TLS | Medium | HTTPS for all services |
| Health checks | Medium | Automatic health monitoring |
| Backup automation | Medium | Scheduled backups |
| Load balancing | Low | Horizontal scaling |

---

## 13. Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Infrastructure Lead | | | |
| DevOps Engineer | | | |
| Technical Lead | | | |

---

*Document Version: 1.0.0*
*Last Updated: 2026-05-13*