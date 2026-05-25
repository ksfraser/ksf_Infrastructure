# Architecture - ksf_FA_CRM

## Document Information
- **Module**: ksf_FA_CRM
- **Version**: 1.0.0
- **Date**: 2026-05-25
- **Status**: Implemented
- **Author**: KSFII Development Team

## 1. Purpose

ksf_FA_CRM is the FrontAccounting platform adapter for the CRM business logic. It bridges FA's procedural PHP ecosystem with the `Ksfraser\CRM\*` domain entities and services from `ksf_CRM`.

## 2. Directory Structure

```
ksf_FA_CRM/
├── hooks.php                     # FA module hooks, security, menu items
├── composer.json                 # Dependencies: ksfraser/traits, ksfraser/exceptions
├── includes/
│   ├── crm_db.inc                # FA database CRUD layer (TB_PREF, db_query)
│   ├── crm_ui.inc                # FA UI components (tables, forms, selectors)
│   ├── crm_tags.inc              # CRM tag type constants (TAG_CUSTOMER=3, etc.)
│   ├── crm_relationships_db.inc  # Contact/account relationship CRUD
│   ├── gedcom_import.php         # GEDCOM 5.5 parser (GedcomImporter class)
│   ├── gedcom_export.php         # GEDCOM 5.5 exporter (GedcomExporter class)
│   ├── EmailImportService.php    # IMAP email import service
│   ├── ksf_FA_CRMDB.php          # Database service singleton
│   └── import.php                # Customer CSV import
├── pages/
│   ├── dashboard.php             # CRM dashboard
│   ├── customers.php             # Customer list (redirects to FA)
│   ├── opportunities.php         # Opportunity CRUD
│   ├── communications.php        # Communication log
│   ├── crm_tags.php              # Tag management admin
│   ├── leads.php                 # Lead management
│   ├── quotes.php                # Quote management
│   ├── realms.php                # Opportunity realm management
│   ├── customer_types.php        # Customer type CRUD
│   ├── territories.php           # Territory CRUD
│   ├── meetings.php              # Meeting management
│   ├── meeting_rooms.php         # Meeting room CRUD
│   ├── email_accounts.php        # Email account CRUD
│   ├── calendar.php              # Calendar view
│   ├── convert_lead.php          # Lead conversion form
│   ├── contact_relationships.php # Person-to-person relationship CRUD
│   ├── account_relationships.php # Account-to-account nested relationship CRUD
│   ├── person_account_roles.php  # Person-account role assignment CRUD
│   ├── life_events.php           # GEDCOM-style life/business events CRUD
│   ├── gedcom_import.php         # GEDCOM file upload & import page
│   ├── gedcom_export.php         # GEDCOM download page
│   └── org_chart.php             # vis-network org chart with tag filters
├── sql/
│   └── install.sql               # 16 CRM tables with @TB_PREF@ placeholders
└── src/Ksfraser/FA/CRM/
    ├── Entities.php               # CRMCustomer, CRMContact, CRMOpportunity, CRMCommunication
    ├── CRMService.php             # CRM service (validation, analytics)
    └── Events.php                 # PSR-14 event classes
```

## 3. FA Integration Points

### Security Sections (SS_CRM = 114 << 8)

| Security Area | Bit | Permission |
|--------------|-----|------------|
| SA_CRM_DASHBOARD | 1 | CRM Dashboard |
| SA_CRM_CUSTOMER | 2 | CRM Customers |
| SA_CRM_OPPORTUNITY | 3 | CRM Opportunities |
| SA_CRM_COMMUNICATION | 4 | CRM Communications |
| SA_CRM_SETUP | 5 | CRM Setup |
| SA_CUSTOMER_TYPE | 6 | Customer Types |
| SA_TERRITORY | 7 | Territories |
| SA_CRM_LEAD | 8 | CRM Leads |
| SA_CRM_QUOTE | 9 | CRM Quotes |
| SA_CRM_REALM | 10 | CRM Realms |
| SA_CRM_MEETING | 11 | CRM Meetings |
| SA_CRM_EMAIL_ACCOUNT | 12 | Email Accounts |
| SA_CRM_TAGS | 13 | CRM Tags |
| SA_CRM_CONTACT_RELATIONSHIPS | 14 | Contact Relationships |
| SA_CRM_ACCOUNT_RELATIONSHIPS | 15 | Account Relationships |
| SA_CRM_PERSON_ACCOUNT_ROLES | 16 | Person-Account Roles |
| SA_CRM_LIFE_EVENTS | 17 | Life Events |
| SA_CRM_GEDCOM | 18 | GEDCOM Import/Export |
| SA_CRM_ORG_CHART | 19 | Org Chart |

### Menu Items (under Sales tab)
- CRM Dashboard (MENU_MAIN)
- CRM Customers (MENU_ENTRY)
- Opportunities (MENU_ENTRY)
- Communications Log (MENU_INQUIRY)
- Contact Relationships (MENU_ENTRY)
- Account Relationships (MENU_ENTRY)
- Person-Account Roles (MENU_ENTRY)
- Life Events (MENU_ENTRY)
- GEDCOM Import/Export (MENU_MAINTENANCE)
- Org Chart (MENU_INQUIRY)
- CRM Setup (MENU_MAINTENANCE)

## 4. Tag System

Uses FA's `0_tags` + `0_tag_associations` tables with CRM-specific type constants:

| Constant | Value | Entity | record_id format |
|----------|-------|--------|-----------------|
| TAG_CUSTOMER | 3 | Customer | debtor_no (varchar) |
| TAG_CONTACT | 4 | Contact | contact.id (int as string) |
| TAG_OPPORTUNITY | 5 | Opportunity | opportunity.id |
| TAG_LEAD | 6 | Lead | lead.id |
| TAG_COMMUNICATION | 7 | Communication | communication.id |

## 5. Dependencies

- ksfraser/exceptions (shared exception library)
- ksfraser/traits (HookQueryProviderTrait, EventEmitterTrait)
- ksfraser/ksf-crm (business logic library)
- ksfraser/rbac (optional: record-level access control)

## 6. Relationship Schema

Sixteen CRM tables total (12 original + 4 new):

### Original Tables (Core CRM)
- `fa_crm_customers` — Extended customer data (linked to debtors_master)
- `fa_crm_contacts` — Contact persons linked to customers
- `fa_crm_opportunities` — Sales opportunities with stage tracking
- `fa_crm_communications` — Communication log entries
- `fa_crm_leads` — Lead management
- `fa_crm_quotes` — Quote management
- `fa_crm_realms` — Opportunity realm categories
- `fa_crm_customer_types` — Customer type taxonomy
- `fa_crm_territories` — Geographic territories
- `fa_crm_meetings` — Meeting records
- `fa_crm_meeting_rooms` — Meeting room resources
- `fa_crm_email_accounts` — Email account configurations

### New Tables (Relationships & Events)

**fa_crm_contact_relationships** — Person-to-person relationships (GEDCOM-inspired)
| Column | Type | Description |
|--------|------|-------------|
| id | int(11) PK AUTO_INCREMENT | Primary key |
| contact_id_1 | int(11) NOT NULL | First contact (FK to crm_contacts) |
| contact_id_2 | int(11) NOT NULL | Second contact (FK to crm_contacts) |
| relationship_type | varchar(50) NOT NULL | Type: spouse, parent, child, sibling, partner, etc. |
| details_json | text NULL | Free-form structured data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**fa_crm_account_relationships** — Account-to-account relationships (nested entities)
| Column | Type | Description |
|--------|------|-------------|
| id | int(11) PK AUTO_INCREMENT | Primary key |
| account_id_1 | int(11) NOT NULL | First account (FK to debtors_master) |
| account_id_2 | int(11) NOT NULL | Second account (FK to debtors_master) |
| relationship_type | varchar(50) NOT NULL | Type: owns, subsidiary, hq, branch, contract, approval |
| details_json | text NULL | Free-form structured data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**fa_crm_person_account_roles** — Person↔Account role assignments
| Column | Type | Description |
|--------|------|-------------|
| id | int(11) PK AUTO_INCREMENT | Primary key |
| contact_id | int(11) NOT NULL | Contact person (FK to crm_contacts) |
| account_id | int(11) NOT NULL | Account (FK to debtors_master) |
| role | varchar(100) NOT NULL | Role: director, manager, employee, shareholder, trustee, beneficiary, signatory |
| is_primary | tinyint(1) DEFAULT 0 | Primary contact flag |
| date_from | date NULL | Role start date |
| date_to | date NULL | Role end date |
| details_json | text NULL | Free-form structured data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

**fa_crm_life_events** — GEDCOM-style life/business events
| Column | Type | Description |
|--------|------|-------------|
| id | int(11) PK AUTO_INCREMENT | Primary key |
| entity_type | varchar(20) NOT NULL | Entity type: person, account, relationship |
| entity_id | int(11) NOT NULL | FK to relevant entity |
| event_type | varchar(50) NOT NULL | GEDCOM tag: BIRT, DEAT, MARR, custom: INCORP, DISSOLVED, ACQUIRED, etc. |
| event_date | date NULL | Event date |
| event_place | varchar(255) NULL | Event location |
| details_json | text NULL | Free-form structured data |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

## 7. GEDCOM Integration

### Import Flow
1. User uploads `.ged` file via `pages/gedcom_import.php`
2. `GedcomImporter` (`includes/gedcom_import.php`) parses GEDCOM 5.5 format:
   - `INDI` records → `fa_crm_contacts` (persons)
   - `FAM` records → `fa_crm_contact_relationships` (spouse/child relationships)
   - Custom `_` tags → `fa_crm_life_events` with `details_json`
3. Deferred resolution: all individuals parsed first, then FAMS/FAMC cross-references resolved
4. Import stats returned (persons created, relationships created, events created, errors)

### Export Flow
1. User selects persons via `pages/gedcom_export.php`
2. `GedcomExporter` (`includes/gedcom_export.php`) generates GEDCOM 5.5:
   - Each person → `INDI` record with `NAME`, `SEX`, `BIRT`/`DEAT` from life events
   - Relationships → `FAM` records with `HUSB`/`WIFE`/`CHIL` cross-references
   - Business relationships → custom `_BUS` tags
   - File served as `.ged` download

## 8. Org Chart

`pages/org_chart.php` renders an interactive relationship graph using vis-network:

- **Force-directed layout** with draggable nodes
- **Node types**: Persons (blue), Accounts (green), Families (orange)
- **Edge labels**: Relationship type displayed on connections
- **Tag filters**: Click a tag on an entity card to filter the visible set
- **View toggle**: Switch between full org view and focused ego-centric view (centered on selected node)
- **Detail panel**: Click a node to see entity details in a side panel
- **Data source**: Combines `fa_crm_contact_relationships`, `fa_crm_account_relationships`, and `fa_crm_person_account_roles`
- **Sales org chart** (business relationships) distinct from HRM org chart (reporting structure)

## 9. RBAC Integration

When ksf_FA_RBAC is active:
- UserProvisioner auto-creates crm_persons/crm_contacts on login
- CRM pages can call `hook_invoke_first('ksf_get_value', 'rbac.buildAccessJoinSql')` for record-level visibility
- CRM events emit CRUD events for RBAC audit logging
