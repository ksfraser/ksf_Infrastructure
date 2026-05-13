# UAT Plan - ksf_Infrastructure

## Document Information
- **Module**: ksf_Infrastructure
- **Version**: 1.0.0
- **Date**: 2026-05-13
- **Status**: Ready for UAT
- **Author**: KSFII Development Team

---

## 1. UAT Objectives

### 1.1 Purpose
Validate that ksf_Infrastructure correctly deploys the complete KSF stack using Podman containers and Ansible automation.

### 1.2 Objectives
1. Verify containers start correctly
2. Confirm database initialization
3. Validate network connectivity
4. Test module distribution
5. Ensure Ansible automation works

---

## 2. Test Scenarios

### 2.1 Deployment

#### UAT-INF-001: Fresh Deployment
**Scenario**: Deploy complete stack from scratch

**Preconditions**: Linux system with Podman installed

**Test Steps**:
1. Clone ksf_Infrastructure repository
2. Navigate to podman directory
3. Copy .env.example to .env
4. Run `podman-compose up -d`
5. Wait 60 seconds
6. Run `podman-compose ps`
7. Verify all containers running

**Expected Result**: Full stack deployed successfully

**Pass Criteria**: [ ] All containers running [ ] No errors in logs [ ] 3 containers visible

---

#### UAT-INF-002: Access FrontAccounting
**Scenario**: Verify FA is accessible after deployment

**Preconditions**: Containers running

**Test Steps**:
1. Open browser
2. Navigate to http://localhost:8080
3. Verify FA login page displayed
4. Enter credentials: admin / admin
5. Click Login
6. Verify dashboard loads

**Expected Result**: FA accessible and functional

**Pass Criteria**: [ ] Page loads [ ] Login works [ ] Dashboard displays

---

#### UAT-INF-003: Access WordPress
**Scenario**: Verify WordPress is accessible after deployment

**Preconditions**: Containers running

**Test Steps**:
1. Open browser
2. Navigate to http://localhost:8081
3. Verify WordPress setup/landing page
4. Complete initial setup (if first access)

**Expected Result**: WordPress accessible

**Pass Criteria**: [ ] Page loads [ ] Setup works [ ] Admin accessible

---

### 2.2 Database Operations

#### UAT-INF-004: Database Connection
**Scenario**: Connect to MariaDB from host

**Preconditions**: Containers running

**Test Steps**:
1. Install MySQL client if needed
2. Connect: `mysql -h localhost -P 3306 -u ksf_user -p`
3. Enter password: ksfuser2024!
4. Run `SHOW DATABASES;`
5. Run `USE ksf_fa;`
6. Run `SHOW TABLES;`

**Expected Result**: Database accessible, FA tables exist

**Pass Criteria**: [ ] Connection successful [ ] ksf_fa exists [ ] Tables visible

---

#### UAT-INF-005: Data Persistence
**Scenario**: Verify data survives container restart

**Preconditions**: Containers running, DB has data

**Test Steps**:
1. Connect to FA
2. Create test data
3. Run `podman-compose down`
4. Wait 10 seconds
5. Run `podman-compose up -d`
6. Wait for startup
7. Verify test data still exists

**Expected Result**: Data persisted across restart

**Pass Criteria**: [ ] Container restart [ ] Data intact [ ] No corruption

---

### 2.3 Module Distribution

#### UAT-INF-006: Access Module Downloader
**Scenario**: Open module downloader in FA

**Preconditions**: FA accessible

**Test Steps**:
1. Login to FA as admin
2. Navigate to Setup menu
3. Click "Download FA Modules"
4. Verify module list displayed
5. Verify at least 10 modules listed

**Expected Result**: Downloader accessible

**Pass Criteria**: [ ] Menu visible [ ] List displays [ ] Modules listed

---

#### UAT-INF-007: Download a Module
**Scenario**: Download and install a module

**Preconditions**: FA accessible, module downloader working

**Test Steps**:
1. Open module downloader
2. Select "ksf_FA_KnowledgeBase"
3. Click "Download"
4. Wait for download
5. Navigate to Setup > Module Administration
6. Find downloaded module
7. Click "Enable"
8. Verify module menu appears

**Expected Result**: Module installed and enabled

**Pass Criteria**: [ ] Download completes [ ] Module in admin [ ] Enable works [ ] Menu appears

---

### 2.4 Networking

#### UAT-INF-008: Internal Communication
**Scenario**: Verify containers can communicate

**Preconditions**: Containers running

**Test Steps**:
1. Open terminal
2. Execute: `podman exec ksf-fa ping -c 3 ksf-mariadb`
3. Execute: `podman exec ksf-wp ping -c 3 ksf-mariadb`
4. Verify all pings successful

**Expected Result**: Internal networking works

**Pass Criteria**: [ ] FA to MariaDB [ ] WP to MariaDB [ ] No packet loss

---

### 2.5 Ansible

#### UAT-INF-009: Ansible Deployment
**Scenario**: Deploy using Ansible playbook

**Preconditions**: Ansible installed

**Test Steps**:
1. Install Ansible: `sudo apt install ansible`
2. Navigate to ansible directory
3. Run: `ansible-playbook -i localhost ksf_playbook.yaml --ask-become-pass`
4. Enter sudo password
5. Wait for completion
6. Verify containers running
7. Verify services accessible

**Expected Result**: Playbook succeeds, stack deployed

**Pass Criteria**: [ ] Playbook runs [ ] No errors [ ] Containers running [ ] Services work

---

### 2.6 Maintenance

#### UAT-INF-010: View Container Logs
**Scenario**: Check container logs for troubleshooting

**Preconditions**: Containers running

**Test Steps**:
1. Run `podman logs ksf-mariadb`
2. Run `podman logs ksf-fa`
3. Run `podman logs ksf-wp`
4. Verify logs are readable
5. Run `podman logs -f ksf-fa` (follow mode)
6. Press Ctrl+C to exit

**Expected Result**: Logs accessible and readable

**Pass Criteria**: [ ] Logs viewable [ ] Timestamps shown [ ] Follow mode works

---

#### UAT-INF-011: Container Status
**Scenario**: Check running container status

**Preconditions**: Containers running

**Test Steps**:
1. Run `podman-compose ps`
2. Verify all containers listed
3. Verify status shows "running"
4. Verify ports mapped
5. Run `podman stats` for resource usage

**Expected Result**: Status accurate

**Pass Criteria**: [ ] All listed [ ] Status correct [ ] Ports shown [ ] Stats available

---

### 2.7 Reset

#### UAT-INF-012: Full Environment Reset
**Scenario**: Reset environment to fresh state

**Preconditions**: Containers running with data

**Test Steps**:
1. Run `podman-compose down -v`
2. Verify containers stopped
3. Verify volumes removed
4. Run `podman-compose up -d`
5. Wait for startup
6. Verify fresh database initialized
7. Verify services accessible

**Expected Result**: Clean reset successful

**Pass Criteria**: [ ] Down succeeds [ ] Volumes removed [ ] Fresh start [ ] DB re-initialized

---

## 3. Test Execution Schedule

### 3.1 Phase 1: Basic Deployment (Day 1)
| Test | Focus |
|------|-------|
| UAT-INF-001 | Fresh deployment |
| UAT-INF-002 | FA access |
| UAT-INF-003 | WP access |

### 3.2 Phase 2: Database (Day 1)
| Test | Focus |
|------|-------|
| UAT-INF-004 | DB connection |
| UAT-INF-005 | Data persistence |

### 3.3 Phase 3: Modules (Day 1)
| Test | Focus |
|------|-------|
| UAT-INF-006 | Downloader access |
| UAT-INF-007 | Module install |

### 3.4 Phase 4: Automation (Day 2)
| Test | Focus |
|------|-------|
| UAT-INF-008 | Networking |
| UAT-INF-009 | Ansible |

### 3.5 Phase 5: Maintenance (Day 2)
| Test | Focus |
|------|-------|
| UAT-INF-010 | Logs |
| UAT-INF-011 | Status |
| UAT-INF-012 | Reset |

---

## 4. Success Criteria

### 4.1 Functional Criteria

| Criteria | Target | Actual |
|----------|--------|--------|
| Container deployment | 100% | - |
| Service accessibility | 100% | - |
| Database operations | 100% | - |
| Module distribution | 100% | - |
| Ansible automation | 100% | - |

### 4.2 Test Summary

| Category | Total | Passed | Failed |
|----------|-------|--------|--------|
| Deployment | 3 | - | - |
| Database | 2 | - | - |
| Modules | 2 | - | - |
| Networking | 1 | - | - |
| Ansible | 1 | - | - |
| Maintenance | 3 | - | - |
| **Total** | **12** | **-** | **-** |

---

## 5. Sign-off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Infrastructure Lead | | | |
| DevOps Engineer | | | |
| Technical Lead | | | |

---

## 6. Appendix

### 6.1 Test Environment

| Component | Version |
|-----------|---------|
| OS | Ubuntu 20.04 |
| Podman | 3.0+ |
| Podman Compose | Latest |
| Ansible | 2.9+ |
| MariaDB | 10.5+ |

### 6.2 Access Credentials

| Service | Username | Password |
|---------|----------|----------|
| FrontAccounting | admin | admin |
| WordPress | admin | admin2024! |
| MariaDB (root) | root | ksfroot2024! |
| MariaDB (app) | ksf_user | ksfuser2024! |

### 6.3 URLs

| Service | URL | Purpose |
|---------|-----|---------|
| FrontAccounting | http://localhost:8080 | ERP |
| WordPress | http://localhost:8081 | CMS |
| MariaDB | localhost:3306 | Database |

---

*Document Version: 1.0.0*
*Last Updated: 2026-05-13*