<?php
$page_security = 'SA_CRM_LIFE_EVENTS';
$path_to_root = "../../..";
include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");

page(_("Life Events"), false, false, "", "");

simple_page_mode(true);

if ($Mode == 'ADD_ITEM' || $Mode == 'UPDATE_ITEM') {
    $input_error = 0;

    if ($_POST['person_id'] == '') {
        $input_error = 1;
        display_error(_("Person is required."));
        set_focus('person_id');
    }
    if ($_POST['event_type'] == '') {
        $input_error = 1;
        display_error(_("Event type is required."));
        set_focus('event_type');
    }

    if ($input_error != 1) {
        if ($selected_id != -1) {
            update_life_event($selected_id, $_POST['event_type'],
                input_date('event_date'), $_POST['event_place'],
                $_POST['description'], $_POST['details_json']);
            display_notification(_("Life event has been updated."));
        } else {
            insert_life_event($_POST['person_id'], $_POST['event_type'],
                input_date('event_date'), $_POST['event_place'],
                $_POST['description'], null, $_POST['details_json']);
            display_notification(_("Life event has been added."));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'DELETE_ITEM') {
    delete_life_event($selected_id);
    display_notification(_("Life event has been deleted."));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM') {
    $myrow = get_life_event($selected_id);
    if ($myrow) {
        $_POST['person_id'] = $myrow['person_id'];
        $_POST['event_type'] = $myrow['event_type'];
        $_POST['event_date'] = $myrow['event_date'];
        $_POST['event_place'] = $myrow['event_place'];
        $_POST['description'] = $myrow['description'];
        $_POST['details_json'] = $myrow['details_json'];
    }
}

if ($Mode == 'RESET') {
    $selected_id = -1;
    $_POST['person_id'] = '';
    $_POST['event_type'] = '';
    $_POST['event_date'] = '';
    $_POST['event_place'] = '';
    $_POST['description'] = '';
    $_POST['details_json'] = '';
}

$filter_person_id = isset($_GET['person_id']) ? $_GET['person_id'] : null;

$person_selector = array('' => _('Select Person')) + get_contact_persons_selector();

$event_types = array('' => _('Select Type')) + all_life_event_types();

if ($filter_person_id && $_POST['person_id'] == '') {
    $_POST['person_id'] = $filter_person_id;
}

start_form();
start_table(TABLESTYLE2);

$heading = $selected_id != -1 ? _("Edit Life Event") : _("Add Life Event");
table_section_title($heading);

select_row(_("Person:"), 'person_id', $_POST['person_id'], $person_selector);
select_row(_("Event Type:"), 'event_type', $_POST['event_type'], $event_types);
date_row(_("Event Date:"), 'event_date');
text_row(_("Event Place:"), 'event_place', null, 50, 50);
textarea_row(_("Description:"), 'description', $_POST['description'], 50, 4);
textarea_row(_("Details (JSON):"), 'details_json', $_POST['details_json'], 50, 3);

end_table(1);
submit_add_or_update_center($selected_id == -1, '', 'both');
end_form();

br();

start_table(TABLESTYLE);
$th = array(_("Person"), _("Event Type"), _("Date"), _("Place"), _("Description"), "", "");
table_header($th);

$sql = "SELECT e.*,
    CONCAT(p.first_name, ' ', p.last_name) AS person_name
    FROM " . TB_PREF . "fa_crm_life_events e
    LEFT JOIN " . TB_PREF . "crm_persons p ON e.person_id = p.id
    ORDER BY e.event_date, e.id";

$result = db_query($sql, "could not get life events");
$k = 0;
while ($row = db_fetch($result)) {
    alt_table_row_color($k);
    label_cell($row['person_name']);
    label_cell($row['event_type']);
    label_cell($row['event_date']);
    label_cell($row['event_place']);
    label_cell($row['description']);
    edit_button_cell("Edit" . $row['id'], _("Edit"));
    delete_button_cell("Delete" . $row['id'], _("Delete"));
    end_row();
}

end_table(1);
page_end();
