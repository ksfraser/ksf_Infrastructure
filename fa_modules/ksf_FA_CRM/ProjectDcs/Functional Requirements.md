# Functional Requirements - ksf_FA_CRM

## Document Information
- **Module**: ksf_FA_CRM
- **Version**: 2.0.0
- **Date**: 2026-05-25
- **Status**: Active
- **Author**: KSFII Development Team

---

## FR-001 Customer Record Management
**Satisfies**: BR-001

- FR-001.1 The system shall link each CRM customer to a `debtors_master` record by `debtor_no`.
- FR-001.2 The system shall store extended attributes: industry, website, employee count, annual revenue, credit rating, account manager, preferred contact method.
- FR-001.3 The system shall support customer type and territory assignment.
- FR-001.4 The system shall track `customer_since` and `last_contact_date` timestamps.
- FR-001.5 The system shall support activation/deactivation (soft delete) of customers.

---

## FR-002 Opportunity Management
**Satisfies**: BR-002

- FR-002.1 The system shall record opportunities with: name, customer, contact, stage, probability, value, expected close date, and realm.
- FR-002.2 The system shall support configurable stages via the realm taxonomy.
- FR-002.3 The system shall allow converting qualified leads into opportunities.
- FR-002.4 The system shall display opportunity pipeline on the CRM dashboard.

---

## FR-003 Communication Log
**Satisfies**: BR-003

- FR-003.1 The system shall log communications with type (call, email, meeting, note), date, direction (inbound/outbound), subject, body, and outcome.
- FR-003.2 Communication records shall be linkable to a customer, contact, and opportunity.
- FR-003.3 The system shall display communications in reverse-chronological order per customer.

---

## FR-004 Lead Management
**Satisfies**: BR-004

- FR-004.1 The system shall capture leads with: source, company, contact details, status, and assigned sales person.
- FR-004.2 The system shall support lead conversion to CRM customer + contact + opportunity records via a conversion form.
- FR-004.3 The system shall preserve the lead source reference on the resulting customer record.

---

## FR-005 Contact Relationships
**Satisfies**: BR-005

- FR-005.1 The system shall store person-to-person relationships in `fa_crm_contact_relationships`.
- FR-005.2 Supported relationship types shall include: spouse, partner, parent, child, sibling, business_partner.
- FR-005.3 Each relationship shall support `start_date`, `end_date`, and free-form `details_json`.
- FR-005.4 The system shall provide CRUD pages for managing contact relationships.

---

## FR-006 Account Relationships
**Satisfies**: BR-006

- FR-006.1 The system shall store account-to-account relationships in `fa_crm_account_relationships`.
- FR-006.2 Supported relationship types shall include: owns, subsidiary, hq, branch, contract, approval.
- FR-006.3 Each relationship shall support `details_json` for free-form structured data.
- FR-006.4 The system shall provide CRUD pages for managing account relationships.

---

## FR-007 Person–Account Roles
**Satisfies**: BR-007

- FR-007.1 The system shall store person-account role assignments in `fa_crm_person_account_roles`.
- FR-007.2 Supported roles shall include: director, manager, employee, shareholder, trustee, beneficiary, signatory.
- FR-007.3 Each assignment shall support `date_from`, `date_to`, `is_primary`, and `details_json`.
- FR-007.4 The system shall provide CRUD pages for managing person-account roles.

---

## FR-008 Life Events
**Satisfies**: BR-008

- FR-008.1 The system shall store life/business events in `fa_crm_life_events`.
- FR-008.2 Each event shall carry: `entity_type` (person/account/relationship), `entity_id`, `event_type` (GEDCOM tag or custom), `event_date`, `event_place`, and `details_json`.
- FR-008.3 Standard GEDCOM event types shall be supported: BIRT, DEAT, MARR.
- FR-008.4 Custom business event types shall be supported: INCORP (incorporation), DISSOLVED, ACQUIRED.
- FR-008.5 The system shall provide CRUD pages for managing life events.

---

## FR-009 GEDCOM Import
**Satisfies**: BR-009

- FR-009.1 The system shall accept GEDCOM 5.5 file uploads via `pages/gedcom_import.php`.
- FR-009.2 `INDI` records shall be imported as CRM person records in `crm_persons`.
- FR-009.3 `FAM` records shall be imported as contact relationships (spouse, parent/child) in `fa_crm_contact_relationships`.
- FR-009.4 `BIRT`, `DEAT`, `MARR` events shall be imported into `fa_crm_life_events`.
- FR-009.5 Custom tags `_EMPLOYER`, `_BENEFICIARY` shall be imported as person-account roles.
- FR-009.6 Deferred resolution shall handle FAMS/FAMC cross-references where the family record follows the individual.
- FR-009.7 The import page shall display a summary table of counts: individuals, families, life events, relationships, roles, and errors.
- FR-009.8 The GEDCOM parsing logic shall reside in the `ksfraser/gedcom` library (`GedcomParser`). The FA adapter (`FaPersonRepository`, `FaRelationshipRepository`, `FaEventRepository`) shall implement the library's repository contracts.

---

## FR-010 GEDCOM Export
**Satisfies**: BR-010

- FR-010.1 The system shall allow exporting all persons or a selected person's network via `pages/gedcom_export.php`.
- FR-010.2 The export shall produce a GEDCOM 5.5 file served as a `.ged` download.
- FR-010.3 `crm_persons` records shall be exported as `INDI` records with `NAME`, `SEX`, `BIRT`, `DEAT` fields.
- FR-010.4 Contact relationships (spouse, parent/child) shall be exported as `FAM` records.
- FR-010.5 Occupations and employer roles shall be exported as `OCCU` and `_EMPLOYER` tags.
- FR-010.6 The GEDCOM generation logic shall reside in the `ksfraser/gedcom` library (`GedcomGenerator`/`ExportService`).

---

## FR-011 Org Chart Visualisation
**Satisfies**: BR-011

- FR-011.1 The system shall render a force-directed relationship graph using vis-network in `pages/org_chart.php`.
- FR-011.2 Node types shall be visually distinct: Persons (blue), Accounts (green), Families/groups (orange).
- FR-011.3 Edge labels shall display the relationship type.
- FR-011.4 Tag-based filtering shall be available via clickable tag chips above the graph.
- FR-011.5 An ego-centric view toggle shall centre the graph on the selected node.
- FR-011.6 Clicking a node shall display entity details in a side panel.
- FR-011.7 The org chart shall source data from `fa_crm_contact_relationships`, `fa_crm_account_relationships`, and `fa_crm_person_account_roles`.

---

## FR-012 Tag Management
**Satisfies**: BR-012

- FR-012.1 The system shall use FA's `0_tags` and `0_tag_associations` tables with CRM-specific type constants (TAG_CUSTOMER=3, TAG_CONTACT=4, TAG_OPPORTUNITY=5, TAG_LEAD=6, TAG_COMMUNICATION=7).
- FR-012.2 An admin page (`pages/crm_tags.php`) shall allow creating, editing, and deleting CRM tag types.

---

## FR-013 Security Areas
**Satisfies**: BR-015

- FR-013.1 All pages shall check the appropriate FA security area constant before rendering.
- FR-013.2 Security areas shall include: `SA_CRM_DASHBOARD`, `SA_CRM_CUSTOMER`, `SA_CRM_OPPORTUNITY`, `SA_CRM_COMMUNICATION`, `SA_CRM_SETUP`, `SA_CUSTOMER_TYPE`, `SA_TERRITORY`, `SA_CRM_LEAD`, `SA_CRM_QUOTE`, `SA_CRM_REALM`, `SA_CRM_MEETING`, `SA_CRM_EMAIL_ACCOUNT`, `SA_CRM_TAGS`, `SA_CRM_CONTACT_RELATIONSHIPS`, `SA_CRM_ACCOUNT_RELATIONSHIPS`, `SA_CRM_PERSON_ACCOUNT_ROLES`, `SA_CRM_LIFE_EVENTS`, `SA_CRM_GEDCOM`, `SA_CRM_ORG_CHART`.

---

## FR-014 Dependencies
**Satisfies**: BR-015

- FR-014.1 The module shall declare `ksfraser/exceptions ^1.2` as a composer dependency.
- FR-014.2 The module shall declare `ksfraser/traits ^1.2` as a composer dependency.
- FR-014.3 The module shall declare `ksfraser/gedcom *` as a composer dependency (sourced from `https://github.com/ksfraser/ksf_CRM_GEDCOM`).
- FR-014.4 The module shall optionally depend on `ksfraser/rbac` for record-level access control.
