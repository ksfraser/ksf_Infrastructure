# Architecture - ksf_Infrastructure

## Document Information
- **Module**: ksf_Infrastructure
- **Version**: 1.0.0
- **Date**: 2026-05-13
- **Status**: Implemented
- **Author**: KSFII Development Team

---

## 1. Technical Architecture

### 1.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         Host System                                 │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                   Podman Container Engine                       │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐│ │
│  │  │ ksf-mariadb │  │   ksf-fa    │  │       ksf-wp            ││ │
│  │  │  MariaDB    │  │FrontAccounting│ │     WordPress          ││ │
│  │  │  10.5+      │  │   2.4.x      │  │      Latest            ││ │
│  │  │             │◄─┤             │  │                        ││ │
│  │  │  Port:3306  │  │  Port:8080   │  │    Port:8081           ││ │
│  │  └─────────────┘  └─────────────┘  └─────────────────────────┘│ │
│  │                                                              │ │
│  │  ┌─────────────────────────────────────────────────────────┐ │ │
│  │  │                   Podman Volumes                        │ │ │
│  │  │  ┌──────────────┐ ┌─────────────┐ ┌─────────────────┐  │ │ │
│  │  │  │ mariadb_data │ │   fa_data   │ │     wp_data     │  │ │ │
│  │  │  └──────────────┘ └─────────────┘ └─────────────────┘  │ │ │
│  │  └─────────────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.2 Component Responsibilities

| Component | Responsibility | Technology |
|-----------|---------------|------------|
| ksf-mariadb | Data persistence | MariaDB 10.5+ |
| ksf-fa | ERP functionality | PHP 7.x, FrontAccounting 2.4 |
| ksf-wp | CMS integration | PHP, WordPress |
| Volumes | Data persistence | Podman volumes |
| Network | Inter-container communication | Podman networks |

---

## 2. Container Configuration

### 2.1 Podman Compose Structure

```yaml
version: '3.8'

services:
  mariadb:
    image: mariadb:latest
    container_name: ksf-mariadb
    environment:
      MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD}
      MARIADB_DATABASE: ${MARIADB_DATABASE}
      MARIADB_USER: ${MARIADB_USER}
      MARIADB_PASSWORD: ${MARIADB_PASSWORD}
    volumes:
      - mariadb_data:/var/lib/mysql
      - ./init-sql:/docker-entrypoint-initdb.d
    ports:
      - "3306:3306"
    networks:
      - ksf_network

  fa:
    build:
      context: .
      dockerfile: containerfiles/FA.Dockerfile
    container_name: ksf-fa
    environment:
      MARIADB_HOST: ksf-mariadb
      MARIADB_DATABASE: ${MARIADB_DATABASE}
      MARIADB_USER: ${MARIADB_USER}
      MARIADB_PASSWORD: ${MARIADB_PASSWORD}
      FA_ADMIN_PASSWORD: ${FA_ADMIN_PASSWORD}
    volumes:
      - fa_data:/var/www/html
    ports:
      - "8080:80"
    depends_on:
      - mariadb
    networks:
      - ksf_network

  wp:
    image: wordpress:latest
    container_name: ksf-wp
    environment:
      WORDPRESS_DB_HOST: ksf-mariadb
      WORDPRESS_DB_NAME: ${MARIADB_DATABASE}
      WORDPRESS_DB_USER: ${MARIADB_USER}
      WORDPRESS_DB_PASSWORD: ${MARIADB_PASSWORD}
    volumes:
      - wp_data:/var/www/html
      - ./containerfiles/uploads.ini:/usr/local/etc/php/conf.d/uploads.ini
    ports:
      - "8081:80"
    depends_on:
      - mariadb
    networks:
      - ksf_network

volumes:
  mariadb_data:
  fa_data:
  wp_data:

networks:
  ksf_network:
    driver: bridge
```

### 2.2 Dockerfile Configuration

```dockerfile
# FrontAccounting Dockerfile
FROM php:7.4-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libmariadb3 \
    libmariadb-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Configure PHP
COPY containerfiles/FA/php.ini /usr/local/etc/php/conf.d/

# Download FrontAccounting
RUN git clone https://github.com/FrontAccounting/FA.git /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Enable mod_rewrite
RUN a2enmod rewrite
```

---

## 3. Network Architecture

### 3.1 Network Configuration

| Network | Driver | Purpose |
|---------|--------|---------|
| ksf_network | bridge | Internal container communication |

### 3.2 Port Mapping

| Service | Internal Port | External Port | Protocol |
|---------|--------------|----------------|----------|
| MariaDB | 3306 | 3306 | TCP |
| FrontAccounting | 80 | 8080 | TCP |
| WordPress | 80 | 8081 | TCP |

### 3.3 Service Discovery

| Service | Hostname | Internal Port |
|---------|----------|---------------|
| MariaDB | ksf-mariadb | 3306 |
| FrontAccounting | ksf-fa | 80 |
| WordPress | ksf-wp | 80 |

---

## 4. Data Architecture

### 4.1 Volume Strategy

| Volume | Mount Point | Contents |
|--------|-------------|----------|
| mariadb_data | /var/lib/mysql | Database files |
| fa_data | /var/www/html | FA application files |
| wp_data | /var/www/html | WordPress files |

### 4.2 Database Initialization

```
init-sql/
└── init.sql
    ├── Create database
    ├── Create users
    ├── Import FA schema
    └── Initial data
```

---

## 5. Ansible Playbook

### 5.1 Playbook Structure

```yaml
---
- name: KSF Infrastructure Deployment
  hosts: localhost
  become: yes
  vars:
    - ksf_install_dir: /opt/ksf
    - ksf_db_name: ksf_fa
    - ksf_db_user: ksf_user

  tasks:
    - name: Install Podman
      package:
        name: podman
        state: present

    - name: Install Podman Compose
      pip:
        name: podman-compose
        state: present

    - name: Create installation directory
      file:
        path: "{{ ksf_install_dir }}"
        state: directory
        mode: '0755'

    - name: Copy container files
      synchronize:
        src: ./
        dest: "{{ ksf_install_dir }}/"

    - name: Start containers
      shell: |
        cd {{ ksf_install_dir }}/podman
        podman-compose up -d
```

---

## 6. Module Distribution Architecture

### 6.1 ksf_fa_downloader

```
┌─────────────────────────────────────────────────────────────┐
│                  ksf_fA_Downloader                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Module Registry (JSON)                  │   │
│  │  - Module name                                      │   │
│  │  - Description                                       │   │
│  │  - Version                                          │   │
│  │  - Download URL                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│                           ▼                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Download Manager                        │   │
│  │  - Fetch module package                             │   │
│  │  - Extract to fa_modules/                           │   │
│  │  - Verify integrity                                 │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│                           ▼                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              FA Module Administration                │   │
│  │  - List available modules                           │   │
│  │  - Enable/disable modules                           │   │
│  │  - Module dependencies                              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 6.2 Available Modules

| Category | Modules |
|----------|---------|
| CRM | ksf_FA_CRM, ksf_FA_Forms, ksf_FA_KnowledgeBase |
| HR | ksf_FA_HRM, ksf_FA_Leave, ksf_FA_Timesheets |
| Projects | ksf_FA_ProjectManagement, ksf_FA_Service |
| Finance | ksf_FA_Assets, ksf_FA_Subscriptions |
| Operations | ksf_FA_Fleet, ksf_FA_Documents |
| Communication | ksf_FA_EmailManager, ksf_FA_CampaignBuilder |
| Integration | ksf_FA_API, ksf_FA_AsteriskPBX, ksf_FA_Workflow |

---

## 7. Security Architecture

### 7.1 Container Isolation

| Layer | Protection |
|-------|------------|
| Container | Linux namespaces, seccomp |
| Network | Isolated bridge network |
| Filesystem | Read-only base images |
| Capabilities | Dropped capabilities |

### 7.2 Secret Management

| Secret | Current | Recommended |
|--------|---------|-------------|
| Database passwords | Environment variables | HashiCorp Vault |
| Admin passwords | Environment variables | Secrets manager |
| API keys | Source code | Environment variables |

### 7.3 Firewall Rules

```bash
# Allow HTTP/HTTPS
iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
iptables -A INPUT -p tcp --dport 8081 -j ACCEPT

# Allow MySQL (internal only)
iptables -A INPUT -p tcp --dport 3306 -s 172.17.0.0/16 -j ACCEPT
```

---

## 8. Monitoring Architecture

### 8.1 Health Checks

| Container | Check | Interval |
|-----------|-------|----------|
| ksf-mariadb | mariadb ping | 30s |
| ksf-fa | HTTP / | 60s |
| ksf-wp | HTTP / | 60s |

### 8.2 Logging

```bash
# View all container logs
podman logs ksf-mariadb
podman logs ksf-fa
podman logs ksf-wp

# Follow logs in real-time
podman logs -f ksf-fa
```

### 8.3 Resource Monitoring

```bash
# Container stats
podman stats

# Volume usage
podman volume inspect ksf_infrastructure_mariadb_data
```

---

## 9. Backup and Recovery

### 9.1 Backup Strategy

| Data | Method | Frequency |
|------|--------|-----------|
| Database | mysqldump | Daily |
| FA files | rsync | Weekly |
| WP files | rsync | Weekly |
| Volumes | podman checkpoint | Monthly |

### 9.2 Recovery Procedures

```bash
# Restore database
podman exec -i ksf-mariadb mysql -u root -p ksf_fa < backup.sql

# Restore FA files
tar -xzf fa_backup.tar.gz -C /path/to/fa_data

# Restore WP files
tar -xzf wp_backup.tar.gz -C /path/to/wp_data
```

---

## 10. Deployment Patterns

### 10.1 Development Environment

```
Host → Podman → [MariaDB:3306, FA:8080, WP:8081]
```

### 10.2 Production Environment

```
Load Balancer → [FA Instance:8080, FA Instance:8080]
                       ↓
              [MariaDB Primary, MariaDB Replica]
```

---

## 11. Performance Tuning

### 11.1 MariaDB Configuration

```ini
[mysqld]
innodb_buffer_pool_size = 256M
max_connections = 100
query_cache_size = 64M
```

### 11.2 PHP Configuration

```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 64M
```

---

## 12. FA Module Hook Query Pattern

### 12.1 Problem Statement

FA module hooks.php files are loaded early by `install_hooks()` during
`session.inc` initialisation. Constants and configuration values defined in
module entry-point scripts (e.g. `cal.php`, `customers.php`) are NOT available
at hooks load time. This means:

- Module A cannot read Module B's configuration unless Module B's entry point
  has been called first in the same request.
- `defined()` checks against constants defined in other entry points return
  false during hook execution, leading to inconsistent behaviour.

### 12.2 Solution: Hook-Based Value Registry

The KSF framework defines a standardised inter-module query protocol using
FA's native `hook_invoke_first()` and `hook_invoke_all()` functions. Each
module's `hooks.php` advertises the values it owns:

```
┌─────────────────┐         hook_invoke_first('ksf_get_value', 'calendar.api_version')
│  Consumer Page  │──────────────────────────────────────────────────────────────────┐
│  (e.g. cal.php) │                                                                  │
└─────────────────┘                                                                  ▼
                                                                            ┌─────────────────┐
                                                                            │  Provider A      │
                                                                            │  ksf_get_value() │── Returns '2.4.3' → hook_invoke_first stops
                                                                            └─────────────────┘
                                                                            ┌─────────────────┐
                                                                            │  Provider B      │
                                                                            │  ksf_get_value() │── Returns null ("not mine"), chain continues
                                                                            └─────────────────┘
```

### 12.3 Protocol

| Hook Name | Direction | Dispatch | Purpose |
|---|---|---|---|
| `ksf_get_value` | Consumer → Single Provider | `hook_invoke_first` | Query a single namespaced value |
| `ksf_get_values` | Consumer → All Providers | `hook_invoke_all` | Query multiple values at once |
| `ksf_set_value` | Sender → All Modules | `hook_invoke_all` | Push a value to interested modules |

### 12.4 Provider Contract

Every module's `hooks.php` SHOULD implement `ksf_get_value()` and
`ksf_get_values()` methods. The method signature follows FA's hook
convention:

```php
function ksf_get_value($key, $opts = array())
{
    // array_key_exists check — not isset() — so null values are valid
    return array_key_exists($key, $this->_values())
        ? $this->_values()[$key]
        : null;
}
```

### 12.5 Key Namespacing

All value keys MUST be namespaced as `<module>.<name>` to prevent
collisions:

```
calendar.api_version     → "2.4.3"
calendar.hooks_version   → "2.0"
rbac.hooks_version       → "2.0"
crm.default_terms        → "Net 30"
```

### 12.6 Consumer Contract

Consumers use `hook_invoke_first` for single-value queries:

```php
$version = hook_invoke_first('ksf_get_value', 'calendar.api_version');
if ($version !== null) {
    // Calendar module is installed — use its advertised version
}
```

And `hook_invoke_all` for bulk queries:

```php
$results = hook_invoke_all('ksf_get_values', [
    'calendar.api_version',
    'rbac.hooks_version',
]);
// $results is an array of arrays, one per module that responded
```

### 12.7 Reference

See `doc/templates/hooks-template.php` for a complete, ready-to-copy
hooks.php template that implements all three hook methods.

---

## 13. Module Development Workflow

### 13.1 Creating a New Module

1. Copy `doc/templates/hooks-template.php` → `fa_modules/ksf_FA_<Name>/hooks.php`
2. Replace `<ModuleName>`, `<NNN>`, `<MODULENAME>` placeholders
3. Add `sql/install.sql` with `@TB_PREF@` placeholders
4. Create page scripts with `add_access_extensions()` guard
5. Add `composer.json` with `ksfraser/*` dependencies
6. Register security areas in `install_access()`

### 13.2 Adding Inter-Module Values

1. Add entries to the `_get_advertised_values()` array in hooks.php
2. Use the `<module>.<name>` namespacing convention
3. Guard PHP constants with `defined()` checks
4. Guard `get_company_pref()` calls with `function_exists()`

---

*Document Version: 1.1.0*
*Last Updated: 2026-05-24*