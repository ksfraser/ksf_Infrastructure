<?php
$page_security = 'SA_CRM_ACCOUNT_RELATIONSHIPS';
$path_to_root = "../../..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");

page(_("Account Relationships"), false, false, "", "");

simple_page_mode(true);

if ($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    $input_error = 0;

    if ($_POST['parent_debtor_no'] == '') {
        $input_error = 1;
        display_error(_("Parent account is required."));
        set_focus('parent_debtor_no');
    }
    if ($_POST['child_debtor_no'] == '') {
        $input_error = 1;
        display_error(_("Child account is required."));
        set_focus('child_debtor_no');
    }
    if ($_POST['relation_type'] == '') {
        $input_error = 1;
        display_error(_("Relation type is required."));
        set_focus('relation_type');
    }

    if ($input_error != 1) {
        $ownership_pct = $_POST['ownership_pct'] !== '' ? $_POST['ownership_pct'] : null;

        if ($selected_id != -1) {
            update_account_relationship($selected_id, $_POST['relation_type'], $ownership_pct,
                input_date('start_date'), input_date('end_date'), $_POST['notes']);
            display_notification(_("Account relationship has been updated."));
        } else {
            insert_account_relationship($_POST['parent_debtor_no'], $_POST['child_debtor_no'],
                $_POST['relation_type'], $ownership_pct,
                input_date('start_date'), input_date('end_date'), $_POST['notes']);
            display_notification(_("Account relationship has been added."));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'DELETE_ITEM') {
    delete_account_relationship($selected_id);
    display_notification(_("Account relationship has been deleted."));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM') {
    $myrow = get_account_relationship($selected_id);
    if ($myrow) {
        $_POST['parent_debtor_no'] = $myrow['parent_debtor_no'];
        $_POST['child_debtor_no'] = $myrow['child_debtor_no'];
        $_POST['relation_type'] = $myrow['relation_type'];
        $_POST['ownership_pct'] = $myrow['ownership_pct'];
        $_POST['start_date'] = $myrow['start_date'];
        $_POST['end_date'] = $myrow['end_date'];
        $_POST['notes'] = $myrow['notes'];
    }
}

if ($Mode == 'RESET') {
    $selected_id = -1;
    $_POST['parent_debtor_no'] = '';
    $_POST['child_debtor_no'] = '';
    $_POST['relation_type'] = '';
    $_POST['ownership_pct'] = '';
    $_POST['start_date'] = '';
    $_POST['end_date'] = '';
    $_POST['notes'] = '';
}

$filter_debtor_no = isset($_GET['debtor_no']) ? $_GET['debtor_no'] : null;

$sql = "SELECT debtor_no, CONCAT(debtor_no, ' - ', name) FROM " . TB_PREF . "debtors_master WHERE !inactive ORDER BY name";
$debtor_result = db_query($sql, "could not get debtors");
$account_selector = array('' => _('Select Account'));
while ($row = db_fetch_row($debtor_result)) {
    $account_selector[$row[0]] = $row[1];
}

$relation_types = array(
    '' => _('Select Type'),
    CRM_REL_OWNS => _('Owns'),
    CRM_REL_SUBSIDIARY => _('Subsidiary'),
    CRM_REL_TRUSTEE_OF => _('Trustee Of'),
    CRM_REL_BENEFICIARY_OF => _('Beneficiary Of'),
);

if ($filter_debtor_no && $_POST['parent_debtor_no'] == '') {
    $_POST['parent_debtor_no'] = $filter_debtor_no;
}

start_form();
start_table(TABLESTYLE2);

$heading = $selected_id != -1 ? _("Edit Account Relationship") : _("Add Account Relationship");
table_section_title($heading);

select_row(_("Parent Account:"), 'parent_debtor_no', $_POST['parent_debtor_no'], $account_selector);
select_row(_("Child Account:"), 'child_debtor_no', $_POST['child_debtor_no'], $account_selector);
select_row(_("Relation Type:"), 'relation_type', $_POST['relation_type'], $relation_types);
text_row(_("Ownership %:"), 'ownership_pct', null, 10, 10);
date_row(_("Start Date:"), 'start_date');
date_row(_("End Date:"), 'end_date');
textarea_row(_("Notes:"), 'notes', $_POST['notes'], 50, 4);

end_table(1);
submit_add_or_update_center($selected_id == -1, '', 'both');
end_form();

br();

start_table(TABLESTYLE);
$th = array(_("Parent"), _("Child"), _("Relation"), _("Ownership %"), _("Start"), _("End"), "", "");
table_header($th);

$sql = "SELECT r.*,
    d_parent.name AS parent_name,
    d_child.name AS child_name
    FROM " . TB_PREF . "fa_crm_account_relationships r
    LEFT JOIN " . TB_PREF . "debtors_master d_parent ON r.parent_debtor_no = d_parent.debtor_no
    LEFT JOIN " . TB_PREF . "debtors_master d_child ON r.child_debtor_no = d_child.debtor_no
    ORDER BY r.id";

$result = db_query($sql, "could not get account relationships");
$k = 0;
while ($row = db_fetch($result)) {
    alt_table_row_color($k);
    label_cell($row['parent_debtor_no'] . ' - ' . $row['parent_name']);
    label_cell($row['child_debtor_no'] . ' - ' . $row['child_name']);
    label_cell($row['relation_type']);
    label_cell($row['ownership_pct'] !== null ? $row['ownership_pct'] . '%' : '');
    label_cell($row['start_date']);
    label_cell($row['end_date']);
    edit_button_cell("Edit" . $row['id'], _("Edit"));
    delete_button_cell("Delete" . $row['id'], _("Delete"));
    end_row();
}

end_table(1);
page_end();
