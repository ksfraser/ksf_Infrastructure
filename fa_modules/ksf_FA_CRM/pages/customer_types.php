<?php
/**
 * Customer Types Management
 *
 * WebERP-style customer type management for FrontAccounting CRM
 */

$page_security = 'SA_CUSTOMER';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);

page(_($help_context = "Customer Types"), false, false, "", $js);

simple_page_mode(true);

//--------------------------------------------------------------------------------------------

function can_process()
{
	if (strlen($_POST['type_name']) == 0) {
		display_error(_("The customer type name cannot be empty."));
		set_focus('type_name');
		return false;
	}

	if ($selected_id && $selected_id == $_POST['type_name']) {
		display_error(_("You cannot change the name of this customer type because it is the default."));
		set_focus('type_name');
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
		// Update existing customer type
		update_customer_type($selected_id, $_POST['type_name'], $_POST['description'],
			$_POST['discount_percent'], $_POST['credit_limit'], $_POST['payment_terms']);

		display_notification(_("Customer type has been updated"));
	} else {
		// Add new customer type
		$selected_id = add_customer_type($_POST['type_name'], $_POST['description'],
			$_POST['discount_percent'], $_POST['credit_limit'], $_POST['payment_terms']);

		display_notification(_("Customer type has been added"));
	}

	$Ajax->activate('_page_body');
}

//--------------------------------------------------------------------------------------------

if (isset($_POST['submit'])) {
	handle_submit($selected_id);
}

if (isset($_POST['delete'])) {
	if (can_delete_customer_type($selected_id)) {
		delete_customer_type($selected_id);
		display_notification(_("Customer type has been deleted"));
		$Ajax->activate('_page_body');
		$selected_id = '';
	} else {
		display_error(_("Cannot delete this customer type because it is used by customers"));
	}
}

//--------------------------------------------------------------------------------------------

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();

customer_types_list_cells(_("Select a customer type:"), 'selected_id', $selected_id, _('New Customer Type'), true);

check_cells(_("Show inactive:"), 'show_inactive', null, true);
end_row();
end_table();

echo '<br>';

if ($selected_id) {
	// Editing existing customer type
	$result = db_query("SELECT * FROM " . TB_PREF . "crm_customer_types WHERE id = " . (int)$selected_id);
	$myrow = db_fetch($result);

	$_POST['type_name'] = $myrow['type_name'];
	$_POST['description'] = $myrow['description'];
	$_POST['discount_percent'] = $myrow['discount_percent'];
	$_POST['credit_limit'] = $myrow['credit_limit'];
	$_POST['payment_terms'] = $myrow['payment_terms'];
	$_POST['inactive'] = $myrow['inactive'];
} else {
	// New customer type
	$_POST['type_name'] = $_POST['description'] = '';
	$_POST['discount_percent'] = 0;
	$_POST['credit_limit'] = 0;
	$_POST['payment_terms'] = '';
	$_POST['inactive'] = 0;
}

start_table(TABLESTYLE2);

text_row(_("Type Name:"), 'type_name', null, 40, 50);
textarea_row(_("Description:"), 'description', null, 40, 3);

percent_row(_("Default Discount Percent:"), 'discount_percent', $_POST['discount_percent']);
amount_row(_("Default Credit Limit:"), 'credit_limit', $_POST['credit_limit']);

payment_terms_list_row(_("Default Payment Terms:"), 'payment_terms', $_POST['payment_terms']);

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

$sql = "SELECT * FROM " . TB_PREF . "crm_customer_types";
if (!$show_inactive)
	$sql .= " WHERE inactive = 0";
$sql .= " ORDER BY type_name";

$result = db_query($sql);

start_table(TABLESTYLE);
table_header(array(_("Type Name"), _("Description"), _("Discount %"), _("Credit Limit"), _("Payment Terms"), "", ""));

while ($myrow = db_fetch($result)) {
	start_row();

	label_cell($myrow['type_name']);
	label_cell($myrow['description']);
	label_cell(number_format($myrow['discount_percent'], 2) . '%');
	amount_cell($myrow['credit_limit']);

	// Get payment terms description
	$terms_result = db_query("SELECT terms FROM " . TB_PREF . "payment_terms WHERE terms_indicator = '" . $myrow['payment_terms'] . "'");
	$terms = db_fetch($terms_result);
	label_cell($terms ? $terms['terms'] : $myrow['payment_terms']);

	edit_button_cell("Edit" . $myrow['id'], _("Edit"));
	delete_button_cell("Delete" . $myrow['id'], _("Delete"));
	end_row();
}

end_table(1);

//--------------------------------------------------------------------------------------------

end_form();

end_page();

//--------------------------------------------------------------------------------------------

function can_delete_customer_type($id)
{
	// Check if any customers are using this type
	$sql = "SELECT COUNT(*) FROM " . TB_PREF . "debtors_master WHERE customer_type_id = " . (int)$id;
	$result = db_query($sql);
	$count = db_fetch($result);

	return $count[0] == 0;
}