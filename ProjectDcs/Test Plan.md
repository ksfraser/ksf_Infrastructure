# Test Plan - ksf_Infrastructure

## Document Information
- **Module**: ksf_Infrastructure
- **Version**: 1.0.0
- **Date**: 2026-05-13
- **Status**: Implemented
- **Author**: KSFII Development Team

---

## 1. Test Overview

### 1.1 Test Objectives
- Verify container deployment works correctly
- Validate database initialization
- Confirm networking between containers
- Test module distribution system
- Validate Ansible automation

### 1.2 Test Environment

| Requirement | Version |
|-------------|---------|
| Podman | 3.0+ |
| Podman Compose | Latest |
| Linux | Ubuntu 20.04+ |

---

## 2. Test Cases

### 2.1 Container Deployment Tests

#### INF-START-001: Start All Containers
**Test ID**: INF-START-001
**Priority**: High

**Test Steps**:
1. Navigate to podman directory
2. Run `podman-compose up -d`
3. Wait 60 seconds
4. Run `podman-compose ps`
5. Verify all 3 containers running

**Expected Result**: All containers running, no errors

---

#### INF-START-002: Container Health Checks
**Test ID**: INF-START-002
**Priority**: High

**Test Steps**:
1. After containers started
2. Check `podman ps` for health status
3. Verify each container healthy
4. Access each service via browser

**Expected Result**: All services accessible

---

#### INF-START-003: Port Mapping
**Test ID**: INF-START-003
**Priority**: High

**Test Steps**:
1. Run `podman-compose ps`
2. Check PORTS column
3. Verify 3306:3306 mapped
4. Verify 8080:80 mapped
5. Verify 8081:80 mapped

**Expected Result**: Ports mapped correctly

---

### 2.2 Database Tests

#### INF-DB-001: Database Initialization
**Test ID**: INF-DB-001
**Priority**: High

**Test Steps**:
1. Start containers for first time
2. Wait for initialization
3. Connect to MariaDB: `podman exec -it ksf-mariadb mysql -u root -p`
4. Run `SHOW DATABASES;`
5. Verify ksf_fa database exists
6. Run `SELECT user FROM mysql.user;`
7. Verify ksf_user exists

**Expected Result**: Database initialized with correct schema and users

---

#### INF-DB-002: Data Persistence
**Test ID**: INF-DB-002
**Priority**: High

**Test Steps**:
1. Create test database
2. Stop containers: `podman-compose down`
3. Start containers: `podman-compose up -d`
4. Verify test database still exists

**Expected Result**: Data persisted across restarts

---

#### INF-DB-003: Database Connection
**Test ID**: INF-DB-003
**Priority**: High

**Test Steps**:
1. Start containers
2. Connect from host: `mysql -h localhost -P 3306 -u ksf_user -p`
3. Run simple query
4. Connect from FA container to ksf-mariadb

**Expected Result**: Connections successful

---

### 2.3 Network Tests

#### INF-NET-001: Internal Communication
**Test ID**: INF-NET-001
**Priority**: High

**Test Steps**:
1. Start containers
2. From FA container: `ping ksf-mariadb`
3. From FA container: `mysql -h ksf-mariadb -u ksf_user -p ksf_fa -e "SELECT 1;"`
4. From WP container: `ping ksf-mariadb`

**Expected Result**: Internal communication works

---

#### INF-NET-002: External Access
**Test ID**: INF-NET-002
**Priority**: High

**Test Steps**:
1. Start containers
2. Access http://localhost:8080 (FA)
3. Access http://localhost:8081 (WP)
4. Verify pages load correctly

**Expected Result**: External access works on mapped ports

---

### 2.4 Module Distribution Tests

#### INF-MOD-001: Module Downloader Access
**Test ID**: INF-MOD-001
**Priority**: High

**Test Steps**:
1. Login to FA
2. Navigate to Setup
3. Find "Download FA Modules" menu
4. Click to open
5. Verify module list displayed
6. Verify descriptions shown

**Expected Result**: Downloader interface accessible

---

#### INF-MOD-002: Download and Install Module
**Test ID**: INF-MOD-002
**Priority**: High

**Test Steps**:
1. Open module downloader
2. Select module (e.g., ksf_FA_CRM)
3. Click Download
4. Verify download progress
5. Navigate to Module Administration
6. Enable downloaded module
7. Verify module appears in menu

**Expected Result**: Module installed and enabled

---

#### INF-MOD-003: Module File Location
**Test ID**: INF-MOD-003
**Priority**: Medium

**Test Steps**:
1. After downloading module
2. Check fa_modules/ directory
3. Verify module directory exists
4. Check permissions: `ls -la fa_modules/`
5. Verify www-data ownership

**Expected Result**: Module in correct location with correct permissions

---

### 2.5 Ansible Tests

#### INF-ANS-001: Playbook Execution
**Test ID**: INF-ANS-001
**Priority**: Medium

**Test Steps**:
1. Install Ansible: `sudo apt install ansible`
2. Navigate to ansible directory
3. Run: `ansible-playbook -i localhost ksf_playbook.yaml --ask-become-pass`
4. Verify playbook completes
5. Verify containers running
6. Verify services accessible

**Expected Result**: Playbook runs successfully, stack deployed

---

#### INF-ANS-002: Idempotent Execution
**Test ID**: INF-ANS-002
**Priority**: Low

**Test Steps**:
1. Run playbook first time
2. Run playbook second time
3. Verify no errors
4. Verify same end state

**Expected Result**: Playbook idempotent

---

### 2.6 Security Tests

#### INF-SEC-001: Default Credentials Work
**Test ID**: INF-SEC-001
**Priority**: High

**Test Steps**:
1. Start containers
2. Login to FA: admin/admin
3. Login to WP: admin/admin2024!
4. Connect to DB: ksf_user/ksfuser2024!

**Expected Result**: Default credentials work (for initial setup)

---

#### INF-SEC-002: Network Isolation
**Test ID**: INF-SEC-002
**Priority**: Medium

**Test Steps**:
1. Start containers
2. From host: `nc -zv localhost 3306` (should work)
3. From external IP: try to connect (should fail if firewall enabled)
4. Verify FA and WP not accessing internet directly

**Expected Result**: Network isolation in place

---

### 2.7 Maintenance Tests

#### INF-MAINT-001: Container Reset
**Test ID**: INF-MAINT-001
**Priority**: Medium

**Test Steps**:
1. Start containers
2. Stop with volumes: `podman-compose down -v`
3. Start again: `podman-compose up -d`
4. Verify database re-initialized
5. Verify FA and WP fresh

**Expected Result**: Fresh start works

---

#### INF-LOGS-001: Log Viewing
**Test ID**: INF-LOGS-001
**Priority**: Low

**Test Steps**:
1. Start containers
2. Run `podman logs ksf-mariadb`
3. Run `podman logs -f ksf-fa`
4. Verify logs accessible
5. Verify `--tail` works

**Expected Result**: Logs viewable correctly

---

## 3. Test Data

### 3.1 Environment Variables

```bash
MARIADB_ROOT_PASSWORD=ksfroot2024!
MARIADB_DATABASE=ksf_fa
MARIADB_USER=ksf_user
MARIADB_PASSWORD=ksfuser2024!
FA_ADMIN_PASSWORD=admin
WP_ADMIN_PASSWORD=admin2024!
```

### 3.2 Service URLs

| Service | URL |
|---------|-----|
| FrontAccounting | http://localhost:8080 |
| WordPress | http://localhost:8081 |
| MariaDB | localhost:3306 |

---

## 4. Pass Criteria

| Category | Target | Actual |
|----------|--------|--------|
| Container deployment | 100% | - |
| Database operations | 100% | - |
| Networking | 100% | - |
| Module distribution | 100% | - |
| Ansible automation | 100% | - |

---

## 5. Test Execution

### 5.1 Prerequisites
```bash
# Install Podman
sudo apt install podman

# Install Podman Compose
pip install podman-compose

# Clone repository
git clone <repo>
cd ksf_Infrastructure
```

### 5.2 Run Tests
```bash
cd podman
podman-compose up -d
# Run tests
podman-compose down
```

---

*Document Version: 1.0.0*
*Last Updated: 2026-05-13*