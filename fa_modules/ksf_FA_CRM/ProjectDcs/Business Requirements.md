# Business Requirements - ksf_FA_CRM

## Document Information
- **Module**: ksf_FA_CRM
- **Version**: 2.0.0
- **Date**: 2026-05-25
- **Status**: Active
- **Author**: KSFII Development Team

---

## BR-001 Customer Relationship Management

**Priority**: High

The system shall allow users to manage customer records, linking them to FrontAccounting's `debtors_master` accounts, and maintain extended CRM attributes (industry, territory, segment, account manager, credit rating, annual revenue).

---

## BR-002 Opportunity Pipeline

**Priority**: High

The system shall allow sales staff to track opportunities through a configurable stage pipeline (Lead → Qualified → Proposal → Negotiation → Won/Lost), with probability weighting and realm categorisation.

---

## BR-003 Communication Log

**Priority**: High

The system shall maintain a time-ordered log of all communications with a customer (emails, calls, meetings, notes), linked to the relevant contact and opportunity.

---

## BR-004 Lead Management

**Priority**: Medium

The system shall support capturing unqualified leads and converting them to CRM customer records when qualified, preserving the lead source and conversion metadata.

---

## BR-005 Contact Relationship Mapping

**Priority**: High

The system shall allow recording personal relationships between contact persons (spouse, parent, child, sibling, partner, business partner), supporting family mapping for wealth management, estate planning, and trust/HoldCo/OpCo structures.

---

## BR-006 Account Hierarchy Mapping

**Priority**: High

The system shall allow recording structural relationships between accounts (subsidiary, owns, headquarters, branch, contract, approval flow), enabling nested entity visualisation of HoldCo/OpCo/trust structures.

---

## BR-007 Person–Account Role Assignment

**Priority**: High

The system shall allow assigning roles for contact persons within accounts (director, manager, employee, shareholder, trustee, beneficiary, signatory), with optional date ranges and a primary contact flag.

---

## BR-008 Life Events (GEDCOM-style)

**Priority**: Medium

The system shall support recording structured life and business events for persons and accounts (birth, death, marriage, incorporation, dissolution, acquisition, etc.), with free-form JSON details for extensibility.

---

## BR-009 GEDCOM Import

**Priority**: Medium

The system shall support importing GEDCOM 5.5 files to bulk-load person records, family relationships, and life events from genealogy or estate-planning systems.

---

## BR-010 GEDCOM Export

**Priority**: Medium

The system shall support exporting selected persons and their relationship network as a GEDCOM 5.5 file, suitable for import into third-party genealogy or estate-planning tools.

---

## BR-011 Relationship Org Chart Visualisation

**Priority**: Medium

The system shall provide an interactive graphical org chart of contact and account relationships, with tag-based filtering, ego-centric view toggle, and a detail panel on node selection.

---

## BR-012 Tag Management

**Priority**: Low

The system shall allow administrators to define and manage tags for customers, contacts, opportunities, leads, and communications, enabling filtering and segmentation across all CRM entities.

---

## BR-013 Territory and Customer Type Taxonomy

**Priority**: Low

The system shall allow administrators to define geographic territories and customer type categories, used for segmentation and reporting.

---

## BR-014 Meeting Management

**Priority**: Low

The system shall allow scheduling and recording meetings with contacts, linked to opportunities and communications, with meeting room resource booking.

---

## BR-015 Security and Access Control

**Priority**: High

All CRM pages shall be protected by FrontAccounting's security area system. Optional integration with ksf_FA_RBAC shall support record-level visibility control.
