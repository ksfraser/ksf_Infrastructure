<?php
$page_security = 'SA_CRM_PERSON_ACCOUNT_ROLES';
$path_to_root = "../../..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");

page(_("Person Account Roles"), false, false, "", "");

simple_page_mode(true);

if ($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    $input_error = 0;

    if ($_POST['person_id'] == '') {
        $input_error = 1;
        display_error(_("Person is required."));
        set_focus('person_id');
    }
    if ($_POST['debtor_no'] == '') {
        $input_error = 1;
        display_error(_("Account is required."));
        set_focus('debtor_no');
    }
    if ($_POST['role'] == '') {
        $input_error = 1;
        display_error(_("Role is required."));
        set_focus('role');
    }

    if ($input_error != 1) {
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;

        if ($selected_id != -1) {
            update_person_account_role($selected_id, $_POST['role'],
                input_date('start_date'), input_date('end_date'), $is_primary, $_POST['notes']);
            display_notification(_("Person account role has been updated."));
        } else {
            insert_person_account_role($_POST['person_id'], $_POST['debtor_no'],
                $_POST['role'], input_date('start_date'), input_date('end_date'), $is_primary, $_POST['notes']);
            display_notification(_("Person account role has been added."));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'DELETE_ITEM') {
    delete_person_account_role($selected_id);
    display_notification(_("Person account role has been deleted."));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM') {
    $myrow = get_person_account_role($selected_id);
    if ($myrow) {
        $_POST['person_id'] = $myrow['person_id'];
        $_POST['debtor_no'] = $myrow['debtor_no'];
        $_POST['role'] = $myrow['role'];
        $_POST['is_primary'] = $myrow['is_primary'];
        $_POST['start_date'] = $myrow['start_date'];
        $_POST['end_date'] = $myrow['end_date'];
        $_POST['notes'] = $myrow['notes'];
    }
}

if ($Mode == 'RESET') {
    $selected_id = -1;
    $_POST['person_id'] = '';
    $_POST['debtor_no'] = '';
    $_POST['role'] = '';
    $_POST['is_primary'] = 0;
    $_POST['start_date'] = '';
    $_POST['end_date'] = '';
    $_POST['notes'] = '';
}

$filter_person_id = isset($_GET['person_id']) ? $_GET['person_id'] : null;
$filter_debtor_no = isset($_GET['debtor_no']) ? $_GET['debtor_no'] : null;

$person_selector = array('' => _('Select Person')) + get_contact_persons_selector();

$sql = "SELECT debtor_no, CONCAT(debtor_no, ' - ', name) FROM " . TB_PREF . "debtors_master WHERE !inactive ORDER BY name";
$debtor_result = db_query($sql, "could not get debtors");
$account_selector = array('' => _('Select Account'));
while ($row = db_fetch_row($debtor_result)) {
    $account_selector[$row[0]] = $row[1];
}

$role_types = array('' => _('Select Role'));
foreach (all_role_types() as $role) {
    $role_types[$role] = ucwords(str_replace('_', ' ', $role));
}

if ($filter_person_id && $_POST['person_id'] == '') {
    $_POST['person_id'] = $filter_person_id;
}
if ($filter_debtor_no && $_POST['debtor_no'] == '') {
    $_POST['debtor_no'] = $filter_debtor_no;
}

start_form();
start_table(TABLESTYLE2);

$heading = $selected_id != -1 ? _("Edit Person Account Role") : _("Add Person Account Role");
table_section_title($heading);

select_row(_("Person:"), 'person_id', $_POST['person_id'], $person_selector);
select_row(_("Account:"), 'debtor_no', $_POST['debtor_no'], $account_selector);
select_row(_("Role:"), 'role', $_POST['role'], $role_types);
check_row(_("Is Primary:"), 'is_primary', $_POST['is_primary']);
date_row(_("Start Date:"), 'start_date');
date_row(_("End Date:"), 'end_date');
textarea_row(_("Notes:"), 'notes', $_POST['notes'], 50, 4);

end_table(1);
submit_add_or_update_center($selected_id == -1, '', 'both');
end_form();

br();

start_table(TABLESTYLE);
$th = array(_("Person"), _("Account"), _("Role"), _("Primary"), _("Start"), _("End"), "", "");
table_header($th);

$sql = "SELECT r.*,
    CONCAT(p.first_name, ' ', p.last_name) AS person_name,
    d.name AS account_name
    FROM " . TB_PREF . "fa_crm_person_account_roles r
    LEFT JOIN " . TB_PREF . "crm_persons p ON r.person_id = p.id
    LEFT JOIN " . TB_PREF . "debtors_master d ON r.debtor_no = d.debtor_no
    ORDER BY r.id";

$result = db_query($sql, "could not get person account roles");
$k = 0;
while ($row = db_fetch($result)) {
    alt_table_row_color($k);
    label_cell($row['person_name']);
    label_cell($row['debtor_no'] . ' - ' . $row['account_name']);
    label_cell(ucwords(str_replace('_', ' ', $row['role'])));
    label_cell($row['is_primary'] ? _("Yes") : _("No"));
    label_cell($row['start_date']);
    label_cell($row['end_date']);
    edit_button_cell("Edit" . $row['id'], _("Edit"));
    delete_button_cell("Delete" . $row['id'], _("Delete"));
    end_row();
}

end_table(1);
page_end();
