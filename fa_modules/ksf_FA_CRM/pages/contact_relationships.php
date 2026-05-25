<?php
$page_security = 'SA_CRM_CONTACT_RELATIONSHIPS';
$path_to_root = "../../..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");

page(_("Contact Relationships"), false, false, "", "");

simple_page_mode(true);

if ($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    $input_error = 0;

    if ($_POST['person_a_id'] == '') {
        $input_error = 1;
        display_error(_("Person A is required."));
        set_focus('person_a_id');
    }
    if ($_POST['person_b_id'] == '') {
        $input_error = 1;
        display_error(_("Person B is required."));
        set_focus('person_b_id');
    }
    if ($_POST['relation_type'] == '') {
        $input_error = 1;
        display_error(_("Relation type is required."));
        set_focus('relation_type');
    }

    if ($input_error != 1) {
        $is_directed = isset($_POST['is_directed']) ? 1 : 0;

        if ($selected_id != -1) {
            update_contact_relationship($selected_id, $_POST['relation_type'], $is_directed,
                input_date('start_date'), input_date('end_date'), $_POST['notes']);
            display_notification(_("Contact relationship has been updated."));
        } else {
            insert_contact_relationship($_POST['person_a_id'], $_POST['person_b_id'],
                $_POST['relation_type'], $is_directed,
                input_date('start_date'), input_date('end_date'), $_POST['notes']);
            display_notification(_("Contact relationship has been added."));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'DELETE_ITEM') {
    delete_contact_relationship($selected_id);
    display_notification(_("Contact relationship has been deleted."));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM') {
    $myrow = get_contact_relationship($selected_id);
    if ($myrow) {
        $_POST['person_a_id'] = $myrow['person_a_id'];
        $_POST['person_b_id'] = $myrow['person_b_id'];
        $_POST['relation_type'] = $myrow['relation_type'];
        $_POST['is_directed'] = $myrow['is_directed'];
        $_POST['start_date'] = $myrow['start_date'];
        $_POST['end_date'] = $myrow['end_date'];
        $_POST['notes'] = $myrow['notes'];
    }
}

if ($Mode == 'RESET') {
    $selected_id = -1;
    $_POST['person_a_id'] = '';
    $_POST['person_b_id'] = '';
    $_POST['relation_type'] = '';
    $_POST['is_directed'] = 0;
    $_POST['start_date'] = '';
    $_POST['end_date'] = '';
    $_POST['notes'] = '';
}

$filter_person_id = isset($_GET['person_id']) ? $_GET['person_id'] : null;

$person_selector = array('' => _('Select Person')) + get_contact_persons_selector();

$relation_types = array(
    '' => _('Select Type'),
    CRM_REL_SPOUSE => _('Spouse'),
    CRM_REL_PARENT => _('Parent'),
    CRM_REL_CHILD => _('Child'),
    CRM_REL_SIBLING => _('Sibling'),
    CRM_REL_BENEFICIARY => _('Beneficiary'),
    CRM_REL_TRUSTEE => _('Trustee'),
);

if ($filter_person_id && $_POST['person_a_id'] == '') {
    $_POST['person_a_id'] = $filter_person_id;
}

start_form();
start_table(TABLESTYLE2);

$heading = $selected_id != -1 ? _("Edit Contact Relationship") : _("Add Contact Relationship");
table_section_title($heading);

select_row(_("Person A:"), 'person_a_id', $_POST['person_a_id'], $person_selector);
select_row(_("Person B:"), 'person_b_id', $_POST['person_b_id'], $person_selector);
select_row(_("Relation Type:"), 'relation_type', $_POST['relation_type'], $relation_types);
check_row(_("Is Directed:"), 'is_directed', $_POST['is_directed']);
date_row(_("Start Date:"), 'start_date');
date_row(_("End Date:"), 'end_date');
textarea_row(_("Notes:"), 'notes', $_POST['notes'], 50, 4);

end_table(1);
submit_add_or_update_center($selected_id == -1, '', 'both');
end_form();

br();

start_table(TABLESTYLE);
$th = array(_("Person A"), _("Person B"), _("Relation"), _("Directed"), _("Start"), _("End"), "", "");
table_header($th);

$sql = "SELECT r.*,
    CONCAT(pa.first_name, ' ', pa.last_name) AS person_a_name,
    CONCAT(pb.first_name, ' ', pb.last_name) AS person_b_name
    FROM " . TB_PREF . "fa_crm_contact_relationships r
    LEFT JOIN " . TB_PREF . "crm_persons pa ON r.person_a_id = pa.id
    LEFT JOIN " . TB_PREF . "crm_persons pb ON r.person_b_id = pb.id
    ORDER BY r.id";

$result = db_query($sql, "could not get contact relationships");
$k = 0;
while ($row = db_fetch($result)) {
    alt_table_row_color($k);
    label_cell($row['person_a_name']);
    label_cell($row['person_b_name']);
    label_cell($row['relation_type']);
    label_cell($row['is_directed'] ? _("Yes") : _("No"));
    label_cell($row['start_date']);
    label_cell($row['end_date']);
    edit_button_cell("Edit" . $row['id'], _("Edit"));
    delete_button_cell("Delete" . $row['id'], _("Delete"));
    end_row();
}

end_table(1);
page_end();
