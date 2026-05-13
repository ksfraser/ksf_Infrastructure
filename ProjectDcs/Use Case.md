# Use Case - ksf_Infrastructure

## Document Information
- **Module**: ksf_Infrastructure
- **Version**: 1.0.0
- **Date**: 2026-05-13
- **Status**: Implemented
- **Author**: KSFII Development Team

---

## 1. Use Case Overview

| Use Case ID | Use Case Name | Actor | Priority |
|-------------|---------------|-------|----------|
| UC-INF-001 | Deploy Development Environment | DevOps | High |
| UC-INF-002 | Access FrontAccounting | User | High |
| UC-INF-003 | Install FA Module | Administrator | High |
| UC-INF-004 | Backup Database | DevOps | Medium |
| UC-INF-005 | Reset Environment | DevOps | Medium |
| UC-INF-006 | View Container Logs | DevOps | Low |
| UC-INF-007 | Configure Environment | DevOps | Medium |

---

## 2. Use Case Details

### UC-INF-001: Deploy Development Environment

**Actor**: DevOps Engineer
**Priority**: High
**Preconditions**: Podman installed, Linux system

**Basic Flow**:
1. DevOps clones ksf_Infrastructure repo
2. Copies .env.example to .env
3. Customizes environment variables
4. Runs `cd podman`
5. Runs `podman-compose up -d`
6. Verifies containers running
7. Accesses services

**Postconditions**: Full KSF stack running

---

### UC-INF-002: Access FrontAccounting

**Actor**: End User
**Priority**: High
**Preconditions**: Containers running

**Basic Flow**:
1. User opens browser
2. Navigates to http://localhost:8080
3. FA login page displayed
4. User enters admin credentials
5. Dashboard loaded

**Postconditions**: User logged into FA

---

### UC-INF-003: Install FA Module

**Actor**: Administrator
**Priority**: High
**Preconditions**: FA accessible, modules available

**Basic Flow**:
1. Admin logs into FA
2. Navigates to Setup > Download Modules
3. Views available modules list
4. Clicks Download on desired module
5. Module downloaded and extracted
6. Navigates to Setup > Module Administration
7. Enables new module
8. Module visible in FA menu

**Alternative Flows**:
- **Module has dependencies**: Warning shown, dependencies listed
- **Download fails**: Error message, retry option

**Postconditions**: Module installed and enabled

---

### UC-INF-004: Backup Database

**Actor**: DevOps Engineer
**Priority**: Medium
**Preconditions**: MariaDB running

**Basic Flow**:
1. DevOps opens terminal
2. Runs mysqldump via container:
   ```
   podman exec ksf-mariadb mysqldump -u root -p ksf_fa > backup.sql
   ```
3. Backup file created locally
4. File stored in backup location
5. Backup timestamp recorded

**Postconditions**: Database backed up to file

---

### UC-INF-005: Reset Environment

**Actor**: DevOps Engineer
**Priority**: Medium
**Preconditions**: Containers running

**Basic Flow**:
1. DevOps stops containers with volume delete:
   ```
   podman-compose down -v
   ```
2. Removes volume manually:
   ```
   podman volume rm ksf_infrastructure_mariadb_data
   ```
3. Restarts fresh environment:
   ```
   podman-compose up -d
   ```
4. Database re-initialized

**Warning**: All data will be lost!

**Postconditions**: Fresh environment running

---

### UC-INF-006: View Container Logs

**Actor**: DevOps Engineer
**Priority**: Low
**Preconditions**: Containers running

**Basic Flow**:
1. DevOps runs `podman logs ksf-fa`
2. FA container logs displayed
3. Uses `-f` to follow live logs
4. Uses `--tail 100` for last 100 lines

**Postconditions**: Logs visible

---

### UC-INF-007: Configure Environment

**Actor**: DevOps Engineer
**Priority**: Medium
**Preconditions**: Container files present

**Basic Flow**:
1. DevOps edits .env file
2. Changes database password
3. Changes FA admin password
4. Saves file
5. Recreates containers:
   ```
   podman-compose down
   podman-compose up -d
   ```

**Alternative Flow**:
- Only change one service: `podman-compose up -d --force-recreate <service>`

**Postconditions**: New configuration applied

---

## 3. Sequence Diagrams

### UC-INF-001: Deploy Environment

```
DevOps          Terminal        Podman          Containers
  │                │               │                │
  │ cd podman      │               │                │
  │────────────────>│               │                │
  │                │               │                │
  │ podman-compose up -d            │                │
  │────────────────────────────────>│                │
  │                │               │                │
  │                │               │ Pull images   │
  │                │               │───────┐        │
  │                │               │       │ Done   │
  │                │               │<──────┘        │
  │                │               │                │
  │                │               │ Start MariaDB │
  │                │               │──────────────>│
  │                │               │                │
  │                │               │  (wait)        │
  │                │               │                │
  │                │               │ Init DB       │
  │                │               │──────────────>│
  │                │               │                │
  │                │               │ Start FA      │
  │                │               │──────────────>│
  │                │               │                │
  │                │               │ Start WP      │
  │                │               │──────────────>│
  │                │               │                │
  │ Success        │               │                │
  │<────────────────────────────────│                │
```

---

## 4. Data Flow

### 4.1 Container Startup Flow

```
podman-compose up -d
    ↓
Read docker-compose.yaml
    ↓
Create ksf_network
    ↓
Create/start mariadb
    ↓
Mount init.sql volume
    ↓
Execute init.sql on first run
    ↓
Create/start fa (depends_on mariadb)
    ↓
Mount fa_data volume
    ↓
Create/start wp (depends_on mariadb)
    ↓
Mount wp_data volume
    ↓
All containers running
```

### 4.2 Module Installation Flow

```
Admin clicks "Download"
    ↓
ksf_fa_downloader fetches module
    ↓
Module archived to fa_modules/
    ↓
Module extracted
    ↓
Admin enables in Module Admin
    ↓
FA hook_invoke_all registers module
    ↓
Module menu items appear
```

---

## 5. Non-Functional Requirements

### 5.1 Performance
- Container startup: < 60 seconds
- Database init: < 30 seconds
- Module download: < 5 minutes

### 5.2 Reliability
- Auto-restart on failure
- Health checks enabled
- Graceful shutdown

### 5.3 Security
- Default passwords must be changed
- Network isolation enabled
- No privileged containers

---

## 6. Use Case Traceability

| UC ID | Related FR | Related Test |
|-------|------------|-------------|
| UC-INF-001 | FR-INF-001, FR-INF-005, FR-INF-009 | INF-START-001 |
| UC-INF-002 | FR-INF-009 | INF-ACCESS-001 |
| UC-INF-003 | FR-INF-010, FR-INF-011, FR-INF-012 | INF-MOD-001 |
| UC-INF-004 | FR-DB-001 | INF-BACKUP-001 |
| UC-INF-005 | FR-INF-020 | INF-RESET-001 |
| UC-INF-006 | FR-INF-004 | INF-LOGS-001 |
| UC-INF-007 | FR-INF-013 | INF-ENV-001 |

---

*Document Version: 1.0.0*
*Last Updated: 2026-05-13*