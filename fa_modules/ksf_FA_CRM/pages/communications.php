<?php
$page_security = 'SA_CUSTOMER';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);

page(_($help_context = "Customer Communications"), false, false, "", $js);

//--------------------------------------------------------------------------------------------

function display_communications_table($debtor_no)
{
	global $SysPrefs;

	$communications = get_customer_communications($debtor_no);

	start_table(TABLESTYLE);
	$th = array(_("Date"), _("Type"), _("Direction"), _("Subject"), _("Contact"), _("Assigned To"), _("Status"), "", "");
	table_header($th);

	$k = 0;
	while ($comm = db_fetch($communications)) {
		alt_table_row_color($k);

		$date = sql2date($comm['created_at']);
		$type = ucfirst($comm['communication_type']);
		$direction = ucfirst($comm['direction']);
		$subject = $comm['subject'] ? $comm['subject'] : _("No Subject");
		$contact = $comm['first_name'] ? $comm['first_name'] . ' ' . $comm['last_name'] : _("General");
		$assigned = $comm['assigned_to'] ? $comm['assigned_to'] : _("Unassigned");
		$status = ucfirst($comm['status']);

		label_cell($date);
		label_cell($type);
		label_cell($direction);
		label_cell($subject);
		label_cell($contact);
		label_cell($assigned);
		label_cell($status);

		edit_button_cell("Edit" . $comm['id'], _("Edit"));
		delete_button_cell("Delete" . $comm['id'], _("Delete"));

		end_row();
	}

	end_table(1);
}

function display_communication_form($debtor_no, $communication_id = null)
{
	global $Ajax;

	if ($communication_id) {
		$comm = get_communication($communication_id);
		$_POST['communication_type'] = $comm['communication_type'];
		$_POST['direction'] = $comm['direction'];
		$_POST['subject'] = $comm['subject'];
		$_POST['message'] = $comm['message'];
		$_POST['contact_id'] = $comm['contact_id'];
		$_POST['assigned_to'] = $comm['assigned_to'];
		$_POST['priority'] = $comm['priority'];
		$_POST['status'] = $comm['status'];
		$_POST['follow_up_required'] = $comm['follow_up_required'];
		$_POST['follow_up_date'] = sql2date($comm['follow_up_date']);
		$_POST['notes'] = $comm['notes'];
	}

	start_form();

	start_table(TABLESTYLE2);

	// Get customer contacts for dropdown
	$contacts = get_customer_contacts($debtor_no);
	$contact_options = array('' => _("General"));
	while ($contact = db_fetch($contacts)) {
		$contact_options[$contact['id']] = $contact['first_name'] . ' ' . $contact['last_name'];
	}

	table_section_title(_("Communication Details"));

	text_row(_("Subject:"), 'subject', null, 60, 100);
	comms_type_list_row(_("Type:"), 'communication_type', null);
	direction_list_row(_("Direction:"), 'direction', null);
	select_row(_("Contact:"), 'contact_id', null, $contact_options, null);
	text_row(_("Assigned To:"), 'assigned_to', null, 30, 50);
	priority_list_row(_("Priority:"), 'priority', null);
	status_list_row(_("Status:"), 'status', null);

	table_section_title(_("Content"));
	textarea_row(_("Message:"), 'message', null, 60, 10);

	table_section_title(_("Follow-up"));
	check_row(_("Follow-up Required:"), 'follow_up_required');
	date_row(_("Follow-up Date:"), 'follow_up_date');
	textarea_row(_("Notes:"), 'notes', null, 60, 3);

	end_table(1);

	submit_center($communication_id ? 'update_communication' : 'add_communication',
		$communication_id ? _("Update Communication") : _("Add Communication"), true, '', 'default');

	end_form();
}

//--------------------------------------------------------------------------------------------

if (isset($_GET['debtor_no'])) {
	$debtor_no = $_GET['debtor_no'];
} else {
	display_error(_("No customer selected."));
	exit;
}

// Get customer name
$sql = "SELECT name FROM " . TB_PREF . "debtors_master WHERE debtor_no = " . db_escape($debtor_no);
$result = db_query($sql);
$customer = db_fetch($result);

if (!$customer) {
	display_error(_("Customer not found."));
	exit;
}

$customer_name = $customer['name'];

if (isset($_POST['add_communication'])) {
	$communication_data = array(
		'debtor_no' => $debtor_no,
		'contact_id' => $_POST['contact_id'],
		'communication_type' => $_POST['communication_type'],
		'direction' => $_POST['direction'],
		'subject' => $_POST['subject'],
		'message' => $_POST['message'],
		'assigned_to' => $_POST['assigned_to'],
		'priority' => $_POST['priority'],
		'status' => $_POST['status'],
		'follow_up_required' => isset($_POST['follow_up_required']) ? 1 : 0,
		'follow_up_date' => $_POST['follow_up_date'],
		'notes' => $_POST['notes'],
		'created_by' => $_SESSION['wa_current_user']->user
	);

	add_communication($communication_data);
	display_notification(_("Communication added successfully."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['update_communication'])) {
	$communication_id = $_POST['communication_id'];
	$communication_data = array(
		'contact_id' => $_POST['contact_id'],
		'communication_type' => $_POST['communication_type'],
		'direction' => $_POST['direction'],
		'subject' => $_POST['subject'],
		'message' => $_POST['message'],
		'assigned_to' => $_POST['assigned_to'],
		'priority' => $_POST['priority'],
		'status' => $_POST['status'],
		'follow_up_required' => isset($_POST['follow_up_required']) ? 1 : 0,
		'follow_up_date' => $_POST['follow_up_date'],
		'notes' => $_POST['notes']
	);

	update_communication($communication_id, $communication_data);
	display_notification(_("Communication updated successfully."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['Delete'])) {
	$communication_id = substr($_POST['Delete'], 6); // Remove "Delete" prefix
	delete_communication($communication_id);
	display_notification(_("Communication deleted."));
	$Ajax->activate('_page_body');
}

//--------------------------------------------------------------------------------------------

start_form();

start_table(TABLESTYLE_NOBORDER);
start_row();
label_cells(_("Customer:"), $customer_name, "class='label'");
end_row();
end_table(1);

end_form();

display_note(_("Communications for customer: ") . $customer_name, 0, 1);

br();

display_communication_form($debtor_no);

br();

display_communications_table($debtor_no);

end_page();