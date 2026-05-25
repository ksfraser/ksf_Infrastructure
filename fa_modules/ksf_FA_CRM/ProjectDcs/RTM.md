# Requirements Traceability Matrix - ksf_FA_CRM

## Document Information
- **Module**: ksf_FA_CRM
- **Version**: 2.0.0
- **Date**: 2026-05-25
- **Status**: Active
- **Author**: KSFII Development Team

---

## Traceability Table

| Business Req | Functional Req(s) | Implementation | Test Coverage | Status |
|---|---|---|---|---|
| BR-001 Customer Records | FR-001.1 – FR-001.5 | `includes/crm_db.inc`, `pages/dashboard.php`, `src/Ksfraser/FA/CRM/Entities.php` | `tests/Unit/PageStructureTest.php` | Implemented |
| BR-002 Opportunity Pipeline | FR-002.1 – FR-002.4 | `pages/opportunities.php`, `includes/crm_db.inc` | PageStructureTest | Implemented |
| BR-003 Communication Log | FR-003.1 – FR-003.3 | `pages/communications.php`, `includes/crm_db.inc` | PageStructureTest | Implemented |
| BR-004 Lead Management | FR-004.1 – FR-004.3 | `pages/leads.php`, `pages/convert_lead.php` | PageStructureTest | Implemented |
| BR-005 Contact Relationships | FR-005.1 – FR-005.4 | `pages/contact_relationships.php`, `includes/crm_relationships_db.inc`, `sql/install.sql` (`fa_crm_contact_relationships`) | PageStructureTest | Implemented |
| BR-006 Account Relationships | FR-006.1 – FR-006.4 | `pages/account_relationships.php`, `includes/crm_relationships_db.inc`, `sql/install.sql` (`fa_crm_account_relationships`) | PageStructureTest | Implemented |
| BR-007 Person–Account Roles | FR-007.1 – FR-007.4 | `pages/person_account_roles.php`, `includes/crm_relationships_db.inc`, `sql/install.sql` (`fa_crm_person_account_roles`) | PageStructureTest | Implemented |
| BR-008 Life Events | FR-008.1 – FR-008.5 | `pages/life_events.php`, `includes/crm_relationships_db.inc`, `sql/install.sql` (`fa_crm_life_events`) | PageStructureTest | Implemented |
| BR-009 GEDCOM Import | FR-009.1 – FR-009.8 | `pages/gedcom_import.php`, `includes/gedcom_import.php` (FA adapter), `ksfraser/gedcom` library (`GedcomParser`, `ImportService`) | `ksf_CRM_GEDCOM` tests (36/36) | Implemented |
| BR-010 GEDCOM Export | FR-010.1 – FR-010.6 | `pages/gedcom_export.php`, `includes/gedcom_export.php` (FA adapter), `ksfraser/gedcom` library (`GedcomGenerator`, `ExportService`) | `ksf_CRM_GEDCOM` tests (36/36) | Implemented |
| BR-011 Org Chart | FR-011.1 – FR-011.7 | `pages/org_chart.php`, vis-network CDN library | PageStructureTest | Implemented |
| BR-012 Tag Management | FR-012.1 – FR-012.2 | `pages/crm_tags.php`, `includes/crm_tags.inc`, FA `0_tags` / `0_tag_associations` | PageStructureTest | Implemented |
| BR-013 Territory/Type Taxonomy | FR-013 (not referenced) | `pages/territories.php`, `pages/customer_types.php` | PageStructureTest | Implemented |
| BR-014 Meeting Management | — | `pages/meetings.php`, `pages/meeting_rooms.php` | PageStructureTest | Implemented |
| BR-015 Security | FR-013.1 – FR-013.2 | `hooks.php` (SA_* constants), `_init/config` | — | Implemented |

---

## Library Traceability

| Library | Version | Purpose | Repo |
|---|---|---|---|
| `ksfraser/exceptions` | ^1.2 | Shared exception hierarchy | github.com/ksfraser/Exceptions |
| `ksfraser/traits` | ^1.2 | Shared traits (EventEmitter, HookQueryProvider) | github.com/ksfraser/Traits |
| `ksfraser/gedcom` | dev-main | GEDCOM 5.5 parsing and generation | github.com/ksfraser/ksf_CRM_GEDCOM |
| `ksfraser/rbac` | dev-master | Record-level access control | github.com/ksfraser/ksf_FA_RBAC |

---

## Schema Traceability

| Table | Business Req | Functional Req |
|---|---|---|
| `fa_crm_customers` | BR-001 | FR-001 |
| `fa_crm_contacts` | BR-001 | FR-001 |
| `fa_crm_opportunities` | BR-002 | FR-002 |
| `fa_crm_communications` | BR-003 | FR-003 |
| `fa_crm_leads` | BR-004 | FR-004 |
| `fa_crm_quotes` | BR-002 | FR-002 |
| `fa_crm_realms` | BR-002 | FR-002 |
| `fa_crm_customer_types` | BR-013 | — |
| `fa_crm_territories` | BR-013 | — |
| `fa_crm_meetings` | BR-014 | — |
| `fa_crm_meeting_rooms` | BR-014 | — |
| `fa_crm_email_accounts` | BR-003 | FR-003 |
| `fa_crm_contact_relationships` | BR-005 | FR-005 |
| `fa_crm_account_relationships` | BR-006 | FR-006 |
| `fa_crm_person_account_roles` | BR-007 | FR-007 |
| `fa_crm_life_events` | BR-008, BR-009, BR-010 | FR-008, FR-009, FR-010 |
