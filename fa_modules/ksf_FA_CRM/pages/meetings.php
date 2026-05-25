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

page(_($help_context = "CRM Meetings"), false, false, "", $js);

//--------------------------------------------------------------------------------------------

function display_meetings_table($filters = array())
{
	global $SysPrefs;

	$meetings = get_meetings($filters);

	start_table(TABLESTYLE);
	$th = array(_("Meeting"), _("Type"), _("Start Date"), _("Location"), _("Customer"), _("Assigned To"), _("Status"), "", "");
	table_header($th);

	$k = 0;
	while ($meeting = db_fetch($meetings)) {
		alt_table_row_color($k);

		$meeting_name = $meeting['meeting_name'];
		$type = ucfirst($meeting['meeting_type']);
		$start_date = sql2date($meeting['start_date']) . ' ' . date('H:i', strtotime($meeting['start_date']));

		// Location display
		$location = '';
		if ($meeting['location_type'] == 'physical') {
			$location = $meeting['room_name'] ?: $meeting['custom_location'];
		} elseif ($meeting['location_type'] == 'virtual') {
			$location = $meeting['conference_url'] ?: 'Virtual Meeting';
		} elseif ($meeting['location_type'] == 'phone') {
			$location = $meeting['phone_number'] ?: 'Phone Conference';
		}

		$customer = $meeting['customer_name'] ?: _("General");
		$assigned = $meeting['assigned_to_name'] ?: _("Unassigned");
		$status = ucfirst($meeting['status']);

		label_cell($meeting_name);
		label_cell($type);
		label_cell($start_date);
		label_cell($location);
		label_cell($customer);
		label_cell($assigned);
		label_cell($status);

		edit_button_cell("Edit" . $meeting['id'], _("Edit"));
		delete_button_cell("Delete" . $meeting['id'], _("Delete"));

		end_row();
	}

	end_table(1);
}

function display_meeting_form($meeting_id = null)
{
	global $Ajax;

	if ($meeting_id) {
		$meeting = get_meeting($meeting_id);
		$_POST['meeting_name'] = $meeting['meeting_name'];
		$_POST['meeting_type'] = $meeting['meeting_type'];
		$_POST['description'] = $meeting['description'];
		$_POST['start_date'] = date('Y-m-d H:i', strtotime($meeting['start_date']));
		$_POST['end_date'] = date('Y-m-d H:i', strtotime($meeting['end_date']));
		$_POST['duration_minutes'] = $meeting['duration_minutes'];
		$_POST['time_zone'] = $meeting['time_zone'];
		$_POST['location_type'] = $meeting['location_type'];
		$_POST['room_id'] = $meeting['room_id'];
		$_POST['custom_location'] = $meeting['custom_location'];
		$_POST['phone_number'] = $meeting['phone_number'];
		$_POST['conference_url'] = $meeting['conference_url'];
		$_POST['meeting_url'] = $meeting['meeting_url'];
		$_POST['dial_in_number'] = $meeting['dial_in_number'];
		$_POST['access_code'] = $meeting['access_code'];
		$_POST['host_pin'] = $meeting['host_pin'];
		$_POST['debtor_no'] = $meeting['debtor_no'];
		$_POST['opportunity_id'] = $meeting['opportunity_id'];
		$_POST['agenda'] = $meeting['agenda'];
		$_POST['preparation_notes'] = $meeting['preparation_notes'];
		$_POST['status'] = $meeting['status'];
		$_POST['priority'] = $meeting['priority'];
		$_POST['assigned_to'] = $meeting['assigned_to'];
		$_POST['reminder_minutes_before'] = $meeting['reminder_minutes_before'];
	}

	start_form();

	start_table(TABLESTYLE2);

	// Basic Information
	table_section_title(_("Meeting Details"));

	text_row_ex(_("Meeting Name:"), 'meeting_name', 50);
	meeting_type_list_row(_("Meeting Type:"), 'meeting_type', null);
	textarea_row(_("Description:"), 'description', null, 60, 3);

	// Date and Time
	table_section_title(_("Date & Time"));

	date_row(_("Start Date:"), 'start_date', '', null, 0, 0, 0, null, true);
	date_row(_("End Date:"), 'end_date', '', null, 0, 0, 0, null, true);
	text_row_ex(_("Duration (minutes):"), 'duration_minutes', 10);
	timezone_list_row(_("Time Zone:"), 'time_zone', 'UTC');

	// Location
	table_section_title(_("Location"));

	location_type_list_row(_("Location Type:"), 'location_type', null);
	meeting_rooms_list_row(_("Meeting Room:"), 'room_id', null);
	text_row_ex(_("Custom Location:"), 'custom_location', 50);
	text_row_ex(_("Phone Number:"), 'phone_number', 30);
	text_row_ex(_("Conference URL:"), 'conference_url', 50);
	text_row_ex(_("Meeting URL:"), 'meeting_url', 50);
	text_row_ex(_("Dial-in Number:"), 'dial_in_number', 30);
	text_row_ex(_("Access Code:"), 'access_code', 20);
	text_row_ex(_("Host PIN:"), 'host_pin', 10);

	// Associations
	table_section_title(_("Associations"));

	customer_list_row(_("Customer:"), 'debtor_no', null, false, true);
	opportunity_list_row(_("Opportunity:"), 'opportunity_id', null);
	text_row_ex(_("Project ID:"), 'project_id', 20);
	text_row_ex(_("Quote ID:"), 'quote_id', 20);
	text_row_ex(_("Campaign ID:"), 'campaign_id', 20);

	// Content
	table_section_title(_("Content"));

	textarea_row(_("Agenda:"), 'agenda', null, 60, 5);
	textarea_row(_("Preparation Notes:"), 'preparation_notes', null, 60, 3);

	// Status and Assignment
	table_section_title(_("Status & Assignment"));

	meeting_status_list_row(_("Status:"), 'status', 'planned');
	priority_list_row(_("Priority:"), 'priority', 'normal');
	user_list_row(_("Assigned To:"), 'assigned_to', null);
	text_row_ex(_("Reminder (minutes before):"), 'reminder_minutes_before', 10, null, null, 15);

	end_table(1);

	submit_center($meeting_id ? 'update_meeting' : 'add_meeting',
		$meeting_id ? _("Update Meeting") : _("Create Meeting"), true, '', 'default');

	end_form();
}

function display_meeting_attendees($meeting_id)
{
	$attendees = get_meeting_attendees($meeting_id);

	if (db_num_rows($attendees) == 0) {
		echo '<p>' . _("No attendees added yet.") . '</p>';
		return;
	}

	start_table(TABLESTYLE);
	table_header(array(_("Name"), _("Type"), _("Role"), _("Email"), _("Phone"), _("Response"), ""));

	while ($attendee = db_fetch($attendees)) {
		start_row();

		$name = '';
		$email = '';
		$phone = '';

		if ($attendee['attendee_type'] == 'employee') {
			$name = $attendee['employee_name'];
		} elseif ($attendee['attendee_type'] == 'contact') {
			$name = $attendee['contact_name'];
			$email = $attendee['contact_email'];
			$phone = $attendee['contact_phone'];
		} elseif ($attendee['attendee_type'] == 'external') {
			$name = $attendee['external_name'];
			$email = $attendee['external_email'];
			$phone = $attendee['external_phone'];
		}

		label_cell($name);
		label_cell(ucfirst($attendee['attendee_type']));
		label_cell(ucfirst($attendee['attendee_role']));
		label_cell($email);
		label_cell($phone);
		label_cell(ucfirst($attendee['response_status']));

		edit_button_cell("EditAttendee" . $attendee['id'], _("Edit"));
		delete_button_cell("DeleteAttendee" . $attendee['id'], _("Remove"));

		end_row();
	}

	end_table();
}

function display_add_attendee_form($meeting_id)
{
	start_form();

	start_table(TABLESTYLE2);

	table_section_title(_("Add Attendee"));

	attendee_type_list_row(_("Attendee Type:"), 'attendee_type', 'contact');
	attendee_role_list_row(_("Role:"), 'attendee_role', 'required');

	// Dynamic fields based on attendee type will be handled by JavaScript
	echo '<tr id="employee_row" style="display: none;"><td class="label">' . _("Employee:") . '</td><td>';
	echo '<select name="employee_id"><option value="">' . _("Select Employee") . '</option></select>';
	echo '</td></tr>';

	echo '<tr id="contact_row"><td class="label">' . _("Contact:") . '</td><td>';
	echo '<select name="contact_id"><option value="">' . _("Select Contact") . '</option></select>';
	echo '</td></tr>';

	echo '<tr id="external_row" style="display: none;"><td class="label">' . _("External Attendee:") . '</td><td>';
	text_row_ex(_("Name:"), 'external_name', 30);
	text_row_ex(_("Email:"), 'external_email', 30);
	text_row_ex(_("Phone:"), 'external_phone', 20);
	echo '</td></tr>';

	textarea_row(_("Notes:"), 'attendee_notes', null, 40, 2);

	end_table(1);

	submit_center('add_attendee', _("Add Attendee"), true, '', 'default');

	end_form();
}

//--------------------------------------------------------------------------------------------

if (isset($_POST['add_meeting'])) {
	$meeting_data = array(
		'meeting_name' => $_POST['meeting_name'],
		'meeting_type' => $_POST['meeting_type'],
		'description' => $_POST['description'],
		'start_date' => $_POST['start_date'],
		'end_date' => $_POST['end_date'],
		'duration_minutes' => $_POST['duration_minutes'] ?: 60,
		'time_zone' => $_POST['time_zone'] ?: 'UTC',
		'location_type' => $_POST['location_type'],
		'room_id' => $_POST['room_id'] ?: null,
		'custom_location' => $_POST['custom_location'],
		'phone_number' => $_POST['phone_number'],
		'conference_url' => $_POST['conference_url'],
		'meeting_url' => $_POST['meeting_url'],
		'dial_in_number' => $_POST['dial_in_number'],
		'access_code' => $_POST['access_code'],
		'host_pin' => $_POST['host_pin'],
		'debtor_no' => $_POST['debtor_no'],
		'opportunity_id' => $_POST['opportunity_id'],
		'project_id' => $_POST['project_id'],
		'quote_id' => $_POST['quote_id'],
		'campaign_id' => $_POST['campaign_id'],
		'agenda' => $_POST['agenda'],
		'preparation_notes' => $_POST['preparation_notes'],
		'status' => $_POST['status'],
		'priority' => $_POST['priority'],
		'assigned_to' => $_POST['assigned_to'],
		'created_by' => $_SESSION['wa_current_user']->user,
		'is_recurring' => 0,
		'reminder_minutes_before' => $_POST['reminder_minutes_before'] ?: 15,
		'ics_uid' => uniqid('FA-CRM-', true)
	);

	$meeting_id = add_meeting($meeting_data);
	display_notification(_("Meeting created successfully."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['update_meeting'])) {
	$meeting_id = $_POST['meeting_id'];
	$meeting_data = array(
		'meeting_name' => $_POST['meeting_name'],
		'meeting_type' => $_POST['meeting_type'],
		'description' => $_POST['description'],
		'start_date' => $_POST['start_date'],
		'end_date' => $_POST['end_date'],
		'duration_minutes' => $_POST['duration_minutes'] ?: 60,
		'time_zone' => $_POST['time_zone'] ?: 'UTC',
		'location_type' => $_POST['location_type'],
		'room_id' => $_POST['room_id'] ?: null,
		'custom_location' => $_POST['custom_location'],
		'phone_number' => $_POST['phone_number'],
		'conference_url' => $_POST['conference_url'],
		'meeting_url' => $_POST['meeting_url'],
		'dial_in_number' => $_POST['dial_in_number'],
		'access_code' => $_POST['access_code'],
		'host_pin' => $_POST['host_pin'],
		'debtor_no' => $_POST['debtor_no'],
		'opportunity_id' => $_POST['opportunity_id'],
		'project_id' => $_POST['project_id'],
		'quote_id' => $_POST['quote_id'],
		'campaign_id' => $_POST['campaign_id'],
		'agenda' => $_POST['agenda'],
		'preparation_notes' => $_POST['preparation_notes'],
		'status' => $_POST['status'],
		'priority' => $_POST['priority'],
		'assigned_to' => $_POST['assigned_to'],
		'is_recurring' => 0,
		'reminder_minutes_before' => $_POST['reminder_minutes_before'] ?: 15
	);

	update_meeting($meeting_id, $meeting_data);
	display_notification(_("Meeting updated successfully."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['Delete'])) {
	$meeting_id = substr($_POST['Delete'], 6);
	delete_meeting($meeting_id);
	display_notification(_("Meeting deleted."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['add_attendee'])) {
	$attendee_data = array(
		'attendee_type' => $_POST['attendee_type'],
		'attendee_role' => $_POST['attendee_role'],
		'response_status' => 'pending',
		'notes' => $_POST['attendee_notes']
	);

	if ($_POST['attendee_type'] == 'employee') {
		$attendee_data['employee_id'] = $_POST['employee_id'];
	} elseif ($_POST['attendee_type'] == 'contact') {
		$attendee_data['contact_id'] = $_POST['contact_id'];
	} elseif ($_POST['attendee_type'] == 'external') {
		$attendee_data['external_name'] = $_POST['external_name'];
		$attendee_data['external_email'] = $_POST['external_email'];
		$attendee_data['external_phone'] = $_POST['external_phone'];
	}

	add_meeting_attendee($_GET['meeting_id'], $attendee_data);
	display_notification(_("Attendee added successfully."));
	$Ajax->activate('_page_body');
} elseif (isset($_POST['DeleteAttendee'])) {
	$attendee_id = substr($_POST['DeleteAttendee'], 13);
	delete_meeting_attendee($attendee_id);
	display_notification(_("Attendee removed."));
	$Ajax->activate('_page_body');
}

//--------------------------------------------------------------------------------------------

$meeting_id = isset($_GET['meeting_id']) ? $_GET['meeting_id'] : null;

if ($meeting_id) {
	// Show specific meeting details
	$meeting = get_meeting($meeting_id);

	if (!$meeting) {
		display_error(_("Meeting not found."));
		exit;
	}

	start_form();

	start_table(TABLESTYLE_NOBORDER);
	start_row();
	label_cells(_("Meeting:"), $meeting['meeting_name'], "class='label'");
	label_cells(_("Customer:"), $meeting['customer_name'] ?: _("General"), "class='label'");
	end_row();
	end_table(1);

	end_form();

	// Meeting details and attendees
	display_meeting_form($meeting_id);

	br();

	display_note(_("Attendees"), 0, 1);
	display_meeting_attendees($meeting_id);

	br();

	display_note(_("Add Attendee"), 0, 1);
	display_add_attendee_form($meeting_id);

} else {
	// Show meetings list
	display_note(_("CRM Meetings"), 0, 1);

	// Filters
	start_form();

	start_table(TABLESTYLE_NOBORDER);
	start_row();
	customer_list_cells(_("Customer:"), 'filter_customer', null, _("All Customers"), true);
	user_list_cells(_("Assigned To:"), 'filter_assigned_to', null, _("All Users"));
	meeting_status_list_row(_("Status:"), 'filter_status', null);
	date_cells(_("From Date:"), 'filter_start_date');
	date_cells(_("To Date:"), 'filter_end_date');
	end_row();
	end_table(1);

	submit_center('filter_meetings', _("Filter"), true, '', 'default');

	end_form();

	br();

	display_meeting_form();

	br();

	display_meetings_table(array(
		'debtor_no' => $_POST['filter_customer'] ?? null,
		'assigned_to' => $_POST['filter_assigned_to'] ?? null,
		'status' => $_POST['filter_status'] ?? null,
		'start_date' => $_POST['filter_start_date'] ?? null,
		'end_date' => $_POST['filter_end_date'] ?? null
	));
}

end_page();
?>