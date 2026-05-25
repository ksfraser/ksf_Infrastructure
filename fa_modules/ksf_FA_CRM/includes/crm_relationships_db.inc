<?php

define('CRM_REL_PARENT',       'parent');
define('CRM_REL_CHILD',        'child');
define('CRM_REL_SPOUSE',       'spouse');
define('CRM_REL_SIBLING',      'sibling');
define('CRM_REL_BENEFICIARY',  'beneficiary');
define('CRM_REL_TRUSTEE',      'trustee');
define('CRM_REL_EMPLOYER',     'employer');
define('CRM_REL_EMPLOYEE',     'employee');
define('CRM_REL_OWNS',         'owns');
define('CRM_REL_SUBSIDIARY',   'subsidiary');
define('CRM_REL_TRUSTEE_OF',   'trustee_of');
define('CRM_REL_BENEFICIARY_OF', 'beneficiary_of');

function all_personal_relation_types() {
    return array(
        CRM_REL_SPOUSE,
        CRM_REL_PARENT,
        CRM_REL_CHILD,
        CRM_REL_SIBLING,
    );
}

function all_business_relation_types() {
    return array(
        CRM_REL_OWNS,
        CRM_REL_SUBSIDIARY,
        CRM_REL_TRUSTEE_OF,
        CRM_REL_BENEFICIARY_OF,
    );
}

function all_role_types() {
    return array(
        'director',
        'beneficiary',
        'trustee',
        'employee',
        'owner',
        'signatory',
        'accountant',
        'legal_contact',
    );
}

function all_life_event_types() {
    return array(
        'BIRT' => _('Birth'),
        'DEAT' => _('Death'),
        'MARR' => _('Marriage'),
        'DIV'  => _('Divorce'),
        'EDUC' => _('Education'),
        'RETI' => _('Retirement'),
        'CUST' => _('Custom'),
    );
}

function get_person_name($person_id) {
    $sql = "SELECT CONCAT(first_name, ' ', last_name) FROM " . TB_PREF . "crm_persons WHERE id = " . db_escape($person_id);
    $result = db_query($sql, "could not get person name");
    $row = db_fetch_row($result);
    return $row ? $row[0] : _("Unknown");
}

function get_account_name($debtor_no) {
    $sql = "SELECT name FROM " . TB_PREF . "debtors_master WHERE debtor_no = " . db_escape($debtor_no);
    $result = db_query($sql, "could not get account name");
    $row = db_fetch_row($result);
    return $row ? $row[0] : _("Unknown");
}

// ===========================================================================
// CONTACT RELATIONSHIPS
// ===========================================================================

function insert_contact_relationship($person_a_id, $person_b_id, $relation_type, $is_directed=0, $start_date=null, $end_date=null, $notes=null) {
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_contact_relationships
        (person_a_id, person_b_id, relation_type, is_directed, start_date, end_date, notes)
        VALUES (" . db_escape($person_a_id) . ", " . db_escape($person_b_id) . ", "
        . db_escape($relation_type) . ", " . db_escape($is_directed) . ", "
        . ($start_date ? db_escape($start_date) : 'NULL') . ", "
        . ($end_date ? db_escape($end_date) : 'NULL') . ", "
        . ($notes ? db_escape($notes) : 'NULL') . ")";
    return db_query($sql, "could not insert contact relationship");
}

function get_contact_relationship($id) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_contact_relationships WHERE id = " . db_escape($id);
    $result = db_query($sql, "could not get contact relationship");
    return db_fetch($result);
}

function update_contact_relationship($id, $relation_type, $is_directed, $start_date, $end_date, $notes) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_contact_relationships SET
        relation_type = " . db_escape($relation_type) . ",
        is_directed = " . db_escape($is_directed) . ",
        start_date = " . ($start_date ? db_escape($start_date) : 'NULL') . ",
        end_date = " . ($end_date ? db_escape($end_date) : 'NULL') . ",
        notes = " . ($notes ? db_escape($notes) : 'NULL') . "
        WHERE id = " . db_escape($id);
    return db_query($sql, "could not update contact relationship");
}

function delete_contact_relationship($id) {
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_contact_relationships WHERE id = " . db_escape($id);
    return db_query($sql, "could not delete contact relationship");
}

function get_contact_relationships_for_person($person_id) {
    $sql = "SELECT r.*,
        p_a.first_name AS a_first, p_a.last_name AS a_last,
        p_b.first_name AS b_first, p_b.last_name AS b_last
        FROM " . TB_PREF . "fa_crm_contact_relationships r
        LEFT JOIN " . TB_PREF . "crm_persons p_a ON r.person_a_id = p_a.id
        LEFT JOIN " . TB_PREF . "crm_persons p_b ON r.person_b_id = p_b.id
        WHERE r.person_a_id = " . db_escape($person_id) . "
        OR r.person_b_id = " . db_escape($person_id) . "
        ORDER BY r.relation_type, r.id";
    return db_query($sql, "could not get contact relationships");
}

function get_contact_persons_selector() {
    $sql = "SELECT id, CONCAT(first_name, ' ', last_name) AS name
        FROM " . TB_PREF . "crm_persons WHERE !inactive ORDER BY last_name, first_name";
    $result = db_query($sql, "could not get persons list");
    $persons = array();
    while ($row = db_fetch($result)) {
        $persons[$row['id']] = $row['name'];
    }
    return $persons;
}

// ===========================================================================
// ACCOUNT RELATIONSHIPS
// ===========================================================================

function insert_account_relationship($parent_debtor_no, $child_debtor_no, $relation_type, $ownership_pct=null, $start_date=null, $end_date=null, $notes=null) {
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_account_relationships
        (parent_debtor_no, child_debtor_no, relation_type, ownership_pct, start_date, end_date, notes)
        VALUES (" . db_escape($parent_debtor_no) . ", " . db_escape($child_debtor_no) . ", "
        . db_escape($relation_type) . ", "
        . ($ownership_pct !== null ? db_escape($ownership_pct) : 'NULL') . ", "
        . ($start_date ? db_escape($start_date) : 'NULL') . ", "
        . ($end_date ? db_escape($end_date) : 'NULL') . ", "
        . ($notes ? db_escape($notes) : 'NULL') . ")";
    return db_query($sql, "could not insert account relationship");
}

function get_account_relationship($id) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_account_relationships WHERE id = " . db_escape($id);
    $result = db_query($sql, "could not get account relationship");
    return db_fetch($result);
}

function update_account_relationship($id, $relation_type, $ownership_pct, $start_date, $end_date, $notes) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_account_relationships SET
        relation_type = " . db_escape($relation_type) . ",
        ownership_pct = " . ($ownership_pct !== null ? db_escape($ownership_pct) : 'NULL') . ",
        start_date = " . ($start_date ? db_escape($start_date) : 'NULL') . ",
        end_date = " . ($end_date ? db_escape($end_date) : 'NULL') . ",
        notes = " . ($notes ? db_escape($notes) : 'NULL') . "
        WHERE id = " . db_escape($id);
    return db_query($sql, "could not update account relationship");
}

function delete_account_relationship($id) {
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_account_relationships WHERE id = " . db_escape($id);
    return db_query($sql, "could not delete account relationship");
}

function get_account_relationships_for_account($debtor_no) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_account_relationships
        WHERE parent_debtor_no = " . db_escape($debtor_no) . "
        OR child_debtor_no = " . db_escape($debtor_no) . "
        ORDER BY relation_type, id";
    return db_query($sql, "could not get account relationships");
}

// ===========================================================================
// PERSON-ACCOUNT ROLES
// ===========================================================================

function insert_person_account_role($person_id, $debtor_no, $role, $start_date=null, $end_date=null, $is_primary=0, $notes=null) {
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_person_account_roles
        (person_id, debtor_no, role, start_date, end_date, is_primary, notes)
        VALUES (" . db_escape($person_id) . ", " . db_escape($debtor_no) . ", "
        . db_escape($role) . ", "
        . ($start_date ? db_escape($start_date) : 'NULL') . ", "
        . ($end_date ? db_escape($end_date) : 'NULL') . ", "
        . db_escape($is_primary) . ", "
        . ($notes ? db_escape($notes) : 'NULL') . ")";
    return db_query($sql, "could not insert person account role");
}

function get_person_account_role($id) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_person_account_roles WHERE id = " . db_escape($id);
    $result = db_query($sql, "could not get person account role");
    return db_fetch($result);
}

function update_person_account_role($id, $role, $start_date, $end_date, $is_primary, $notes) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_person_account_roles SET
        role = " . db_escape($role) . ",
        start_date = " . ($start_date ? db_escape($start_date) : 'NULL') . ",
        end_date = " . ($end_date ? db_escape($end_date) : 'NULL') . ",
        is_primary = " . db_escape($is_primary) . ",
        notes = " . ($notes ? db_escape($notes) : 'NULL') . "
        WHERE id = " . db_escape($id);
    return db_query($sql, "could not update person account role");
}

function delete_person_account_role($id) {
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_person_account_roles WHERE id = " . db_escape($id);
    return db_query($sql, "could not delete person account role");
}

function get_roles_for_person($person_id) {
    $sql = "SELECT r.*, d.name AS account_name
        FROM " . TB_PREF . "fa_crm_person_account_roles r
        LEFT JOIN " . TB_PREF . "debtors_master d ON r.debtor_no = d.debtor_no
        WHERE r.person_id = " . db_escape($person_id) . "
        ORDER BY r.role, r.id";
    return db_query($sql, "could not get person roles");
}

function get_roles_for_account($debtor_no) {
    $sql = "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) AS person_name
        FROM " . TB_PREF . "fa_crm_person_account_roles r
        LEFT JOIN " . TB_PREF . "crm_persons p ON r.person_id = p.id
        WHERE r.debtor_no = " . db_escape($debtor_no) . "
        ORDER BY r.is_primary DESC, r.role, r.id";
    return db_query($sql, "could not get account roles");
}

// ===========================================================================
// LIFE EVENTS
// ===========================================================================

function insert_life_event($person_id, $event_type, $event_date=null, $event_place=null, $description=null, $gedcom_tag=null, $details_json=null) {
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_life_events
        (person_id, event_type, event_date, event_place, description, gedcom_tag, details_json)
        VALUES (" . db_escape($person_id) . ", " . db_escape($event_type) . ", "
        . ($event_date ? db_escape($event_date) : 'NULL') . ", "
        . ($event_place ? db_escape($event_place) : 'NULL') . ", "
        . ($description ? db_escape($description) : 'NULL') . ", "
        . ($gedcom_tag ? db_escape($gedcom_tag) : 'NULL') . ", "
        . ($details_json ? db_escape($details_json) : 'NULL') . ")";
    return db_query($sql, "could not insert life event");
}

function get_life_event($id) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_life_events WHERE id = " . db_escape($id);
    $result = db_query($sql, "could not get life event");
    return db_fetch($result);
}

function update_life_event($id, $event_type, $event_date, $event_place, $description, $details_json) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_life_events SET
        event_type = " . db_escape($event_type) . ",
        event_date = " . ($event_date ? db_escape($event_date) : 'NULL') . ",
        event_place = " . ($event_place ? db_escape($event_place) : 'NULL') . ",
        description = " . ($description ? db_escape($description) : 'NULL') . ",
        details_json = " . ($details_json ? db_escape($details_json) : 'NULL') . "
        WHERE id = " . db_escape($id);
    return db_query($sql, "could not update life event");
}

function delete_life_event($id) {
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_life_events WHERE id = " . db_escape($id);
    return db_query($sql, "could not delete life event");
}

function get_life_events_for_person($person_id) {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_life_events
        WHERE person_id = " . db_escape($person_id) . "
        ORDER BY event_date, event_type";
    return db_query($sql, "could not get life events");
}
