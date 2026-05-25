<?php
/**
 * Email Account Management for CRM
 *
 * Manage SMTP/IMAP email accounts for automated email import
 */

$page_security = 'SA_CUSTOMER';
$path_to_root = "../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);

page(_($help_context = "Email Account Management"), false, false, "", $js);

$selected_id = get_post('account_id', '');

//--------------------------------------------------------------------------------------------

function can_process()
{
	if (strlen($_POST['account_name']) == 0)
	{
		display_error(_("Account name cannot be empty"));
		set_focus('account_name');
		return false;
	}

	if (strlen($_POST['email_address']) == 0)
	{
		display_error(_("Email address cannot be empty"));
		set_focus('email_address');
		return false;
	}

	if (!filter_var($_POST['email_address'], FILTER_VALIDATE_EMAIL)) {
		display_error(_("Please enter a valid email address"));
		set_focus('email_address');
		return false;
	}

	if (strlen($_POST['server_host']) == 0)
	{
		display_error(_("Server host cannot be empty"));
		set_focus('server_host');
		return false;
	}

	if (!is_numeric($_POST['server_port']) || $_POST['server_port'] < 1 || $_POST['server_port'] > 65535) {
		display_error(_("Server port must be a number between 1 and 65535"));
		set_focus('server_port');
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

	if ($selected_id)
	{
		// Update email account
		update_email_account($selected_id, [
			'account_name' => $_POST['account_name'],
			'email_address' => $_POST['email_address'],
			'server_host' => $_POST['server_host'],
			'server_port' => (int)$_POST['server_port'],
			'encryption' => $_POST['encryption'],
			'username' => $_POST['username'],
			'password' => $_POST['password'] ? encrypt($_POST['password']) : null,
			'auto_import' => isset($_POST['auto_import']) ? 1 : 0,
			'import_frequency' => (int)$_POST['import_frequency'],
			'last_import' => $_POST['last_import'] ? date2sql($_POST['last_import']) : null,
			'is_active' => isset($_POST['is_active']) ? 1 : 0
		]);

		display_notification(_("Email account has been updated"));
	}
	else
	{
		// Add new email account
		$selected_id = add_email_account([
			'account_name' => $_POST['account_name'],
			'email_address' => $_POST['email_address'],
			'server_host' => $_POST['server_host'],
			'server_port' => (int)$_POST['server_port'],
			'encryption' => $_POST['encryption'],
			'username' => $_POST['username'],
			'password' => $_POST['password'] ? encrypt($_POST['password']) : null,
			'auto_import' => isset($_POST['auto_import']) ? 1 : 0,
			'import_frequency' => (int)$_POST['import_frequency'],
			'is_active' => isset($_POST['is_active']) ? 1 : 0
		]);

		if ($selected_id)
		{
			display_notification(_("Email account has been added"));
		}
	}

	$Ajax->activate('_page_body');
}

//--------------------------------------------------------------------------------------------

if (isset($_POST['submit']))
{
	handle_submit($selected_id);
}

if (isset($_POST['delete']))
{
	if (!can_delete_email_account($selected_id))
	{
		display_error(_("Cannot delete the email account because it has associated communications"));
	}
	else
	{
		delete_email_account($selected_id);
		display_notification(_("Email account has been deleted"));
		$Ajax->activate('_page_body');
		$selected_id = '';
	}
}

if (isset($_POST['test_connection']))
{
	$test_result = test_email_connection([
		'server_host' => $_POST['server_host'],
		'server_port' => (int)$_POST['server_port'],
		'encryption' => $_POST['encryption'],
		'username' => $_POST['username'],
		'password' => $_POST['password']
	]);

	if ($test_result['success']) {
		display_notification(_("Connection test successful"));
	} else {
		display_error(_("Connection test failed: ") . $test_result['error']);
	}
}

//--------------------------------------------------------------------------------------------

start_form();

if (db_has_email_accounts())
{
	start_table(TABLESTYLE_NOBORDER);
	start_row();

	email_account_list_cells(_("Select an email account: "), 'account_id', $selected_id,
		_('New Email Account'), true, check_value('show_inactive'));

	check_cells(_("Show inactive:"), 'show_inactive', null, true);
	end_row();
	end_table();

	if (get_post('show_inactive') == 1)
		$show_inactive = 1;
	else
		$show_inactive = 0;
}
else
{
	hidden('account_id', $selected_id);
}

echo '<br>';

div_start('account_details');

if (!$selected_id)
{
	table_section_title(_("Email Account Information"));
}
else
{
	table_section_title(_("Email Account Information"));
}

start_table(TABLESTYLE2);

if ($selected_id) {
	hidden('account_id', $selected_id);
	hidden('selected_id', $selected_id);
}

text_row(_("Account Name:"), 'account_name', @$_POST['account_name'], 40, 50);
email_row(_("Email Address:"), 'email_address', @$_POST['email_address'], 40, 100);

end_table(1);

table_section_title(_("Server Configuration"));

start_table(TABLESTYLE2);

text_row(_("Server Host:"), 'server_host', @$_POST['server_host'], 40, 100);
text_row(_("Server Port:"), 'server_port', @$_POST['server_port'], 10, 5);

encryption_list_row(_("Encryption:"), 'encryption', @$_POST['encryption']);

text_row(_("Username:"), 'username', @$_POST['username'], 40, 100);
password_row(_("Password:"), 'password', @$_POST['password']);

end_table(1);

table_section_title(_("Import Settings"));

start_table(TABLESTYLE2);

check_row(_("Auto Import:"), 'auto_import', @$_POST['auto_import']);

frequency_list_row(_("Import Frequency (minutes):"), 'import_frequency', @$_POST['import_frequency']);

if ($selected_id) {
	date_row(_("Last Import:"), 'last_import', @$_POST['last_import'], true);
}

check_row(_("Active:"), 'is_active', @$_POST['is_active']);

end_table(1);

div_end();

submit_center_first('submit', _("Save Account"), '', 'default');
submit_center_last('test_connection', _("Test Connection"), '', true);

if ($selected_id) {
	submit_js_confirm('delete', _("Are you sure you want to delete this email account?"));
	submit_center_last('delete', _("Delete Account"), '', true);
}

end_form();

end_page();

//--------------------------------------------------------------------------------------------

function email_account_list_cells($label, $name, $selected_id = null, $none_option = false, $submit_on_change = false, $show_inactive = false)
{
	global $all_items;

	if ($show_inactive)
		$where = "inactive = 1";
	else
		$where = "inactive = 0";

	$sql = "SELECT id, account_name, email_address, is_active FROM " . TB_PREF . "crm_email_accounts WHERE $where ORDER BY account_name";

	return combo_input($name, $selected_id, $sql, 'id', 'account_name',
		array(
			'spec_option' => $none_option === false ? _("Select account") : $none_option,
			'order' => 'account_name',
			'spec_id' => '',
			'select_submit' => $submit_on_change,
			'async' => false
		));
}

function encryption_list_row($label, $name, $value = null)
{
	$encryptions = array(
		'' => _("None"),
		'ssl' => _("SSL"),
		'tls' => _("TLS")
	);

	echo '<tr><td class="label">' . $label . '</td><td>';
	echo array_selector($name, $value, $encryptions);
	echo '</td></tr>';
}

function frequency_list_row($label, $name, $value = null)
{
	$frequencies = array(
		15 => _("15 minutes"),
		30 => _("30 minutes"),
		60 => _("1 hour"),
		120 => _("2 hours"),
		240 => _("4 hours"),
		480 => _("8 hours"),
		1440 => _("Daily")
	);

	echo '<tr><td class="label">' . $label . '</td><td>';
	echo array_selector($name, $value, $frequencies);
	echo '</td></tr>';
}

function db_has_email_accounts()
{
	$sql = "SELECT COUNT(*) FROM " . TB_PREF . "crm_email_accounts";
	$result = db_query($sql);
	$row = db_fetch($result);
	return $row[0] > 0;
}

function encrypt($text)
{
	// Simple encryption for demo - in production use proper encryption
	return base64_encode($text);
}