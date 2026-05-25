<?php
/**
 * Sales Territories Management
 *
 * WebERP-style territory management for FrontAccounting CRM
 */

$page_security = 'SA_CUSTOMER';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);

page(_($help_context = "Sales Territories"), false, false, "", $js);

simple_page_mode(true);

//--------------------------------------------------------------------------------------------

function can_process()
{
	if (strlen($_POST['territory_name']) == 0) {
		display_error(_("The territory name cannot be empty."));
		set_focus('territory_name');
		return false;
	}

	return true;
}

//--------------------------------------------------------------------------------------------

function handle_submit(&$selected_id)
{
	global $Ajax;

	if (!can_process())
		return;

	if ($selected_id) {
		// Update existing territory
		update_territory($selected_id, $_POST['territory_name'], $_POST['description'],
			$_POST['sales_person'], $_POST['region']);

		display_notification(_("Territory has been updated"));
	} else {
		// Add new territory
		$selected_id = add_territory($_POST['territory_name'], $_POST['description'],
			$_POST['sales_person'], $_POST['region']);

		display_notification(_("Territory has been added"));
	}

	$Ajax->activate('_page_body');
}

//--------------------------------------------------------------------------------------------

if (isset($_POST['submit'])) {
	handle_submit($selected_id);
}

if (isset($_POST['delete'])) {
	if (can_delete_territory($selected_id)) {
		delete_territory($selected_id);
		display_notification(_("Territory has been deleted"));
		$Ajax->activate('_page_body');
		$selected_id = '';
	} else {
		display_error(_("Cannot delete this territory because it is used by customers"));
	}
}

//--------------------------------------------------------------------------------------------

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();

territories_list_cells(_("Select a territory:"), 'selected_id', $selected_id, _('New Territory'), true);

check_cells(_("Show inactive:"), 'show_inactive', null, true);
end_row();
end_table();

echo '<br>';

if ($selected_id) {
	// Editing existing territory
	$result = db_query("SELECT * FROM " . TB_PREF . "crm_territories WHERE id = " . (int)$selected_id);
	$myrow = db_fetch($result);

	$_POST['territory_name'] = $myrow['territory_name'];
	$_POST['description'] = $myrow['description'];
	$_POST['sales_person'] = $myrow['sales_person'];
	$_POST['region'] = $myrow['region'];
	$_POST['inactive'] = $myrow['inactive'];
} else {
	// New territory
	$_POST['territory_name'] = $_POST['description'] = $_POST['sales_person'] = $_POST['region'] = '';
	$_POST['inactive'] = 0;
}

start_table(TABLESTYLE2);

text_row(_("Territory Name:"), 'territory_name', null, 40, 50);
textarea_row(_("Description:"), 'description', null, 40, 3);
text_row(_("Sales Person:"), 'sales_person', null, 40, 50);
text_row(_("Region:"), 'region', null, 40, 50);

if ($selected_id) {
	record_status_list_row(_("Status:"), 'inactive');
}

end_table(1);

submit_add_or_update_center($selected_id == '', '', 'both');

//--------------------------------------------------------------------------------------------

if (get_post('show_inactive') == 1)
	$show_inactive = 1;
else
	$show_inactive = 0;

$sql = "SELECT * FROM " . TB_PREF . "crm_territories";
if (!$show_inactive)
	$sql .= " WHERE inactive = 0";
$sql .= " ORDER BY territory_name";

$result = db_query($sql);

start_table(TABLESTYLE);
table_header(array(_("Territory Name"), _("Description"), _("Sales Person"), _("Region"), "", ""));

while ($myrow = db_fetch($result)) {
	start_row();

	label_cell($myrow['territory_name']);
	label_cell($myrow['description']);
	label_cell($myrow['sales_person']);
	label_cell($myrow['region']);

	edit_button_cell("Edit" . $myrow['id'], _("Edit"));
	delete_button_cell("Delete" . $myrow['id'], _("Delete"));
	end_row();
}

end_table(1);

//--------------------------------------------------------------------------------------------

end_form();

end_page();

//--------------------------------------------------------------------------------------------

function can_delete_territory($id)
{
	// Check if any customers are using this territory
	$sql = "SELECT COUNT(*) FROM " . TB_PREF . "debtors_master WHERE territory_id = " . (int)$id;
	$result = db_query($sql);
	$count = db_fetch($result);

	return $count[0] == 0;
}