# KSF Module Dependency Graph & Installation Guide

**Generated:** May 2026  
**Purpose:** Determine what to install to test any module

---

## Core Dependency Hierarchy

```
ksf_CRM (Base Business Logic)
├── ksf_CRM_UI
├── ksf_FA_CRM
│
ksf_Workflow (Automation Engine)
├── ksf_Workflow_UI
│
ksf_HRM (Human Resources)
├── ksf_HRM_UI
├── ksf_FA_HRM
│
ksf_Leave (Leave Management)
├── ksf_Leave_UI
├── ksf_FA_Leave
│
ksf_OrgChart (Organization Chart)
├── ksf_OrgChart_UI
├── ksf_FA_OrgChart
│
ksf_Notes (Notes System)
├── ksf_Notes_UI
│
ksf_Teams (Team Management)
├── ksf_Teams_UI
├── ksf_FA_Teams
│
ksf_Timesheets (Time Tracking)
├── ksf_Timesheets_UI
├── ksf_FA_Timesheets
│
ksf_EmailManager (Email Campaigns)
├── ksf_EmailManager_UI
├── ksf_FA_EmailManager
│
ksf_WarrantyManagement (Warranty Tracking)
├── ksf_WarrantyManagement_UI
├── ksf_FA_WarrantyManagement
│
ksf_SupportTickets (Support Tickets)
├── ksf_SupportTickets_UI
├── ksf_FA_SupportTickets
│
ksf_ProjectManagement (Projects/Tasks)
├── ksf_ProjectManagement_UI
├── ksf_FA_ProjectManagement
│
ksf_Calendar (Events/Calendar)
├── ksf_Calendar_UI
├── ksf_FA_Calendar
│
ksf_FA_API (REST/SOAP + CRM Compatibility)
└── (Standalone - no KSF module dependencies)

ksf_Marketing (Marketing Campaigns)
├── (standalone)

ksf_Recruitment (Job Applications)
├── (standalone)

ksf_Training (Training Sessions)
├── (standalone)

ksf_Gantt (Project Timeline Charts)
├── (standalone)

ksf_Onboarding (Employee Onboarding)
├── (standalone)

ksf_Inventory (Stock Management)
├── (standalone)

ksf_Documents (Document Management)
├── (standalone)

ksf_Tracking (Issue Tracking)
└── (standalone)
```

---

## Shared Libraries

```
ksfraser/exceptions     ← CRM, EmailManager, ProjectManagement, Workflow
ksfraser/database       ← All _UI modules + HRM, Leave, OrgChart
ksfraser/ksf-modulesdao ← All _UI modules
ksfraser/event          ← Workflow, WarrantyManagement, SupportTickets
ksfraser/genericinterface ← Calendar, Calendar_UI, ProjectManagement
```

---

## Minimal Install Sets by Use Case

### For CRM Testing
```bash
composer require ksfraser/ksf-crm
composer require ksfraser/ksf-workflow
composer require ksfraser/ksf-supporttickets
composer require ksfraser/ksf-emailmanager
composer require ksfraser/ksf-marketing
```

### For HR/Leave Testing
```bash
composer require ksfraser/ksf-hrm
composer require ksfraser/ksf-leave
composer require ksfraser/ksf-timesheets
```

### For Project Management Testing
```bash
composer require ksfraser/ksf-projectmanagement
composer require ksfraser/ksf-calendar
composer require ksfraser/ksf-gantt
composer require ksfraser/ksf-workflow
```

### For Full FrontAccounting Integration
```bash
composer require \
  ksfraser/ksf-fa-crm \
  ksfraser/ksf-fa-hrm \
  ksfraser/ksf-fa-leave \
  ksfraser/ksf-fa-calendar \
  ksfraser/ksf-fa-projectmanagement \
  ksfraser/ksf-fa-supporttickets \
  ksfraser/ksf-fa-warrantymanagement \
  ksfraser/ksf-fa-teams \
  ksfraser/ksf-fa-emailmanager
```

---

## Module-by-Module Dependency Map

| Module | Requires | Test Command |
|--------|----------|---------------|
| ksf_CRM | ksfraser/exceptions | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_CRM_UI | ksf_CRM, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Workflow | ksfraser/event | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Workflow_UI | ksf_Workflow, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_HRM | ksf_CRM, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_HRM_UI | ksf_HRM, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Leave | ksf_HRM, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Leave_UI | ksf_Leave, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_OrgChart | ksf_CRM, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_OrgChart_UI | ksf_OrgChart, ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Notes | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Notes_UI | ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Teams | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Teams_UI | ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Timesheets | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Timesheets_UI | ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_EmailManager | ksfraser/exceptions | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_EmailManager_UI | ksfraser/database | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_WarrantyManagement | ksfraser/event | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_WarrantyManagement_UI | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_SupportTickets | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_SupportTickets_UI | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_ProjectManagement | ksfraser/exceptions, doctrine/dbal | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_ProjectManagement_UI | (standalone) | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Calendar | doctrine/dbal, eluceo/ical, psr/* | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_Calendar_UI | ksf_Calendar | `composer install && ./vendor/bin/phpunit --testdox` |
| ksf_FA_API | (standalone - mock data) | `composer install && ./vendor/bin/phpunit --testdox` |

---

## FA Adapter Modules (Require Base Module)

| FA Adapter | Requires |
|------------|----------|
| ksf_FA_CRM | ksf_CRM |
| ksf_FA_HRM | ksf_HRM |
| ksf_FA_Leave | ksf_Leave |
| ksf_FA_OrgChart | ksf_OrgChart |
| ksf_FA_Calendar | ksf_Calendar |
| ksf_FA_ProjectManagement | ksf_ProjectManagement |
| ksf_FA_SupportTickets | ksf_SupportTickets |
| ksf_FA_WarrantyManagement | ksf_WarrantyManagement |
| ksf_FA_Teams | ksf_Teams |
| ksf_FA_EmailManager | ksf_EmailManager |

---

## Quick Install All Tests

```bash
# Install dependencies for ALL business logic modules
for module in \
  ksf_CRM \
  ksf_Workflow \
  ksf_HRM \
  ksf_Leave \
  ksf_OrgChart \
  ksf_Notes \
  ksf_Teams \
  ksf_Timesheets \
  ksf_EmailManager \
  ksf_WarrantyManagement \
  ksf_SupportTickets \
  ksf_ProjectManagement \
  ksf_Calendar \
  ksf_FA_API \
  ksf_Marketing \
  ksf_Recruitment \
  ksf_Training \
  ksf_Gantt \
  ksf_Onboarding \
  ksf_Performance \
  ksf_DataIO \
  ksf_Inventory \
  ksf_Documents \
  ksf_JobDescriptions \
  ksf_Roster \
  ksf_Tracking \
  ksf_AsteriskPBX \
  ksf_CampaignBuilder \
  ksf_Forms \
  ksf_ESS \
  ksf_Infrastructure \
  ksf_ModuleBuilder \
  ksf_ModulesDAO \
  ksf_SIPPhone \
  ksf_TravelExpense \
  ksf_KnowledgeBase; do
  
  echo "=== Installing $module ==="
  cd /home/kevin/Documents/$module
  composer install --no-interaction 2>&1 | tail -3
  ./vendor/bin/phpunit --testdox 2>&1 | tail -1
done
```

---

## Clarification: Calendar as Foundational Module

**Calendar** (`ksf_Calendar`) is a foundational module that integrates with many other modules:

### Integration Points:

| Module | Integration Type | Description |
|--------|-----------------|-------------|
| `ksf_ProjectManagement` | Calendar pulls PM tasks | Tasks appear in calendar with priority/due dates |
| `ksf_CRM` | Calendar pulls CRM activities | Calls, meetings, anniversaries synced |
| `ksf_HRM` | Calendar shows shifts | Roster shifts displayed in calendar |
| `ksf_Workflow` | Calendar triggers workflows | Status changes trigger rescheduling |

### Calendar Features:

- **Multi-Source Aggregation**: Pulls from PM, CRM, HRM, client dates
- **Status Tracking**: Meeting statuses (planned/held/not-held/rescheduled), Call outcomes (RNA/VMail/followup)
- **Unscheduled Task Sidebar**: Tasks without dates shown, drag to schedule
- **Shift Schedule View**: Morning/Afternoon/Night/Swing with color coding
- **Status-Based Colors**: Bright for planned, dimmed for completed

### Recommended Install Order for Testing:

```bash
# 1. First install Calendar (foundation)
composer require ksfraser/ksf-calendar

# 2. Then modules that feed into calendar
composer require ksfraser/ksf-crm
composer require ksfraser/ksf-project-management
composer require ksfraser/ksf-hrm

# 3. Workflow for automation
composer require ksfraser/ksf-workflow
```

---

## Testing Matrix

| Business Area | Core Modules | FA Adapters | UI Modules |
|---------------|-------------|-------------|------------|
| **CRM** | ksf_CRM | ksf_FA_CRM | ksf_CRM_UI |
| **HRM** | ksf_HRM | ksf_FA_HRM | ksf_HRM_UI |
| **Leave** | ksf_Leave | ksf_FA_Leave | ksf_Leave_UI |
| **Projects** | ksf_ProjectManagement | ksf_FA_ProjectManagement | ksf_ProjectManagement_UI |
| **Calendar** | ksf_Calendar | ksf_FA_Calendar | ksf_Calendar_UI |
| **Tickets** | ksf_SupportTickets | ksf_FA_SupportTickets | ksf_SupportTickets_UI |
| **Workflow** | ksf_Workflow | - | ksf_Workflow_UI |
| **Email** | ksf_EmailManager | ksf_FA_EmailManager | ksf_EmailManager_UI |

---

## Running Tests for a Specific Area

```bash
# CRM Area
cd /home/kevin/Documents/ksf_CRM && composer install && ./vendor/bin/phpunit

# HRM Area  
cd /home/kevin/Documents/ksf_HRM && composer install && ./vendor/bin/phpunit

# Project Management Area
cd /home/kevin/Documents/ksf_ProjectManagement && composer install && ./vendor/bin/phpunit

# Full FA Integration (all adapters)
cd /home/kevin/Documents/ksf_FA_API && composer install && ./vendor/bin/phpunit
```