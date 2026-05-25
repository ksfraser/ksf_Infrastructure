<?php
$page_security = 'SA_CUSTOMER';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

page(_($help_context = "Meeting Rooms"));

simple_page_mode(true);

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM')
{

	$input_error = 0;

	if (strlen($_POST['room_name']) == 0)
	{
		$input_error = 1;
		display_error(_("The room name cannot be empty."));
		set_focus('room_name');
	}

	if ($input_error != 1)
	{
		if ($selected_id != -1)
		{
			update_meeting_room($selected_id, $_POST);
			display_notification(_('Selected meeting room has been updated'));
		}
		else
		{
			add_meeting_room($_POST);
			display_notification(_('New meeting room has been added'));
		}
		$Mode = 'RESET';
	}
}

if ($Mode == 'Delete')
{
	delete_meeting_room($selected_id);
	display_notification(_('Selected meeting room has been deleted'));
	$Mode = 'RESET';
}

if ($Mode == 'RESET')
{
	$selected_id = -1;
	unset($_POST);
}

//-----------------------------------------------------------------------------------

$result = get_meeting_rooms(check_value('show_inactive'));

start_form();
start_table(TABLESTYLE, "width=80%");

$th = array(_("Room Name"), _("Type"), _("Location"), _("Capacity"), _("Equipment"), _("Active"), "", "");
inactive_control_column($th);
table_header($th);

$k = 0;
while ($myrow = db_fetch($result))
{
	alt_table_row_color($k);

	label_cell($myrow["room_name"]);
	label_cell(ucfirst($myrow["room_type"]));
	label_cell($myrow["location"]);
	label_cell($myrow["capacity"]);
	label_cell($myrow["equipment"]);
	label_cell($myrow["phone_number"]);
	yesno_cell($myrow["active"], _("Yes"), _("No"));
	inactive_control_cell($myrow["id"], !$myrow["active"], 'meeting_rooms', 'id');
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
		$myrow = get_meeting_room($selected_id);

		$_POST['room_name'] = $myrow["room_name"];
		$_POST['room_type'] = $myrow["room_type"];
		$_POST['location'] = $myrow["location"];
		$_POST['capacity'] = $myrow["capacity"];
		$_POST['equipment'] = $myrow["equipment"];
		$_POST['phone_number'] = $myrow["phone_number"];
		$_POST['conference_url'] = $myrow["conference_url"];
		$_POST['active'] = $myrow["active"];
	}
	hidden('selected_id', $selected_id);
}

text_row_ex(_("Room Name:"), 'room_name', 50);

$room_types = array(
	'physical' => _("Physical Room"),
	'virtual' => _("Virtual Room"),
	'phone' => _("Phone Conference")
);
array_selector_row(_("Room Type:"), 'room_type', null, $room_types);

text_row_ex(_("Location:"), 'location', 50);
text_row_ex(_("Capacity:"), 'capacity', 10);
textarea_row(_("Equipment:"), 'equipment', null, 50, 3);
text_row_ex(_("Phone Number:"), 'phone_number', 30);
text_row_ex(_("Conference URL:"), 'conference_url', 50);

yesno_row(_("Active:"), 'active', null, _("Yes"), _("No"));

end_table(1);

submit_add_or_update_center($selected_id == -1, '', 'both');

end_form();

end_page();
?>