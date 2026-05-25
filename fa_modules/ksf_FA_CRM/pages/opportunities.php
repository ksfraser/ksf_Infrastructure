<?php
$page_security = 'SA_CRM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

page(_($help_context = "CRM Opportunities"));

simple_page_mode(true);

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM')
{

	$input_error = 0;

	if (strlen($_POST['name']) == 0)
	{
		$input_error = 1;
		display_error(_("The opportunity name cannot be empty."));
		set_focus('name');
	}
	if (strlen($_POST['customer_id']) == 0)
	{
		$input_error = 1;
		display_error(_("You must select a customer."));
		set_focus('customer_id');
	}
	if (strlen($_POST['stage']) == 0)
	{
		$input_error = 1;
		display_error(_("You must select a sales stage."));
		set_focus('stage');
	}
	if (strlen($_POST['expected_close_date']) == 0)
	{
		$input_error = 1;
		display_error(_("You must enter an expected close date."));
		set_focus('expected_close_date');
	}

	if ($input_error != 1)
	{
		if ($selected_id != -1)
		{
			update_opportunity($selected_id, $_POST['customer_id'], $_POST['name'], $_POST['description'],
				$_POST['stage'], $_POST['amount'], $_POST['probability'], $_POST['expected_close_date'],
				$_POST['assigned_to'], $_POST['source']);
			display_notification(_('Selected opportunity has been updated'));
		}
		else
		{
			add_opportunity($_POST['customer_id'], $_POST['name'], $_POST['description'],
				$_POST['stage'], $_POST['amount'], $_POST['probability'], $_POST['expected_close_date'],
				$_POST['assigned_to'], $_POST['source']);
			display_notification(_('New opportunity has been added'));
		}
		$Mode = 'RESET';
	}
}

if ($Mode == 'Delete')
{
	delete_opportunity($selected_id);
	display_notification(_('Selected opportunity has been deleted'));
	$Mode = 'RESET';
}

if ($Mode == 'RESET')
{
	$selected_id = -1;
	unset($_POST);
}

//-----------------------------------------------------------------------------------

$result = get_opportunities(check_value('show_inactive'));

start_form();
start_table(TABLESTYLE, "width=80%");

$th = array(_("Name"), _("Customer"), _("Stage"), _("Amount"), _("Probability"), _("Expected Close"), _("Assigned To"), "", "");
inactive_control_column($th);
table_header($th);

$k = 0;
while ($myrow = db_fetch($result))
{
	alt_table_row_color($k);

	label_cell($myrow["name"]);
	label_cell($myrow["customer_name"]);
	label_cell($myrow["stage"]);
	amount_cell($myrow["amount"]);
	label_cell($myrow["probability"] . "%");
	label_cell(sql2date($myrow["expected_close_date"]));
	label_cell($myrow["assigned_to_name"]);
	inactive_control_cell($myrow["id"], $myrow["inactive"], 'opportunities', 'id');
	edit_button_cell("Edit".$myrow["id"], _("Edit"));
	delete_button_cell("Delete".$myrow["id"], _("Delete"));
	end_row();
}

inactive_control_row($th);
end_table(1);

//-----------------------------------------------------------------------------------

start_table(TABLESTYLE2);

if ($selected_id != -1)
{
	if ($Mode == 'Edit') {
		$myrow = get_opportunity($selected_id);

		$_POST['customer_id'] = $myrow["customer_id"];
		$_POST['name'] = $myrow["name"];
		$_POST['description'] = $myrow["description"];
		$_POST['stage'] = $myrow["stage"];
		$_POST['amount'] = $myrow["amount"];
		$_POST['probability'] = $myrow["probability"];
		$_POST['expected_close_date'] = sql2date($myrow["expected_close_date"]);
		$_POST['assigned_to'] = $myrow["assigned_to"];
		$_POST['source'] = $myrow["source"];
	}
	hidden('selected_id', $selected_id);
}

customer_list_row(_("Customer:"), 'customer_id', null, false, true);
text_row_ex(_("Opportunity Name:"), 'name', 50);
textarea_row(_("Description:"), 'description', null, 35, 5);
sales_stage_list_row(_("Sales Stage:"), 'stage');
amount_row(_("Estimated Amount:"), 'amount');
percent_row(_("Probability:"), 'probability');
date_row(_("Expected Close Date:"), 'expected_close_date');
user_list_row(_("Assigned To:"), 'assigned_to');
text_row_ex(_("Lead Source:"), 'source', 30);

end_table(1);

submit_add_or_update_center($selected_id == -1, '', 'both');

end_form();

end_page();
?>