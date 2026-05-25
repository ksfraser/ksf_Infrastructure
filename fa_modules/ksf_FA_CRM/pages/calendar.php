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

// Add calendar-specific JavaScript
$js .= "
function changeMonth(year, month) {
    var form = document.forms[0];
    form.year.value = year;
    form.month.value = month;
    form.submit();
}

function showMeetingDetails(meeting_id) {
    window.open('/modules/CRM/pages/meetings.php?meeting_id=' + meeting_id, 'meeting_details', 'width=800,height=600,scrollbars=yes');
}
";

page(_($help_context = "CRM Calendar"), false, false, "", $js);

//--------------------------------------------------------------------------------------------

function display_calendar($year, $month, $meetings = array())
{
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $days_in_month = date('t', $first_day);
    $day_of_week = date('w', $first_day);

    // Adjust for Monday start (0 = Sunday, 6 = Saturday)
    $day_of_week = ($day_of_week == 0) ? 6 : $day_of_week - 1;

    $prev_month = $month - 1;
    $prev_year = $year;
    if ($prev_month == 0) {
        $prev_month = 12;
        $prev_year--;
    }

    $next_month = $month + 1;
    $next_year = $year;
    if ($next_month == 13) {
        $next_month = 1;
        $next_year++;
    }

    echo "<div class='calendar-container' style='max-width: 1000px; margin: 0 auto;'>";

    // Calendar header
    echo "<div class='calendar-header' style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;'>";
    echo "<button type='button' onclick='changeMonth($prev_year, $prev_month)' class='btn btn-outline-secondary btn-sm'>« " . _("Previous") . "</button>";
    echo "<h2 style='margin: 0;'>" . date('F Y', $first_day) . "</h2>";
    echo "<button type='button' onclick='changeMonth($next_year, $next_month)' class='btn btn-outline-secondary btn-sm'>" . _("Next") . " »</button>";
    echo "</div>";

    // Calendar grid
    echo "<table class='calendar-table' style='width: 100%; border-collapse: collapse; border: 1px solid #dee2e6;'>";

    // Day headers
    echo "<thead><tr>";
    $day_names = array(_("Mon"), _("Tue"), _("Wed"), _("Thu"), _("Fri"), _("Sat"), _("Sun"));
    foreach ($day_names as $day_name) {
        echo "<th style='padding: 10px; text-align: center; background-color: #f8f9fa; border: 1px solid #dee2e6; font-weight: bold;'>$day_name</th>";
    }
    echo "</tr></thead>";

    echo "<tbody>";

    // Calendar cells
    $day_counter = 1;
    $current_day = 1;

    for ($week = 0; $week < 6; $week++) {
        echo "<tr>";

        for ($day = 0; $day < 7; $day++) {
            echo "<td style='height: 120px; vertical-align: top; padding: 5px; border: 1px solid #dee2e6;";

            if ($week == 0 && $day < $day_of_week) {
                // Previous month days
                echo " background-color: #f8f9fa; color: #6c757d;";
            } elseif ($current_day > $days_in_month) {
                // Next month days
                echo " background-color: #f8f9fa; color: #6c757d;";
            } else {
                // Current month days
                $is_today = ($current_day == date('j') && $month == date('n') && $year == date('Y'));
                if ($is_today) {
                    echo " background-color: #e3f2fd; border: 2px solid #2196f3;";
                }
            }

            echo "'>";

            if ($week == 0 && $day < $day_of_week) {
                // Previous month
                $prev_days = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
                echo "<div style='font-size: 12px; color: #6c757d;'>" . ($prev_days - $day_of_week + $day + 1) . "</div>";
            } elseif ($current_day <= $days_in_month) {
                // Current month
                echo "<div style='font-weight: bold; margin-bottom: 5px;'>$current_day</div>";

                // Show meetings for this day
                $day_meetings = get_meetings_for_date($year, $month, $current_day, $meetings);
                foreach ($day_meetings as $meeting) {
                    $time = date('H:i', strtotime($meeting['start_date']));
                    $status_color = get_meeting_status_color($meeting['status']);

                    echo "<div style='font-size: 11px; margin: 2px 0; padding: 2px 4px; background-color: $status_color; color: white; border-radius: 3px; cursor: pointer;' onclick='showMeetingDetails({$meeting['id']})'>";
                    echo "<strong>$time</strong> " . htmlspecialchars(substr($meeting['meeting_name'], 0, 20));
                    if (strlen($meeting['meeting_name']) > 20) echo "...";
                    echo "</div>";
                }

                $current_day++;
            } else {
                // Next month
                echo "<div style='font-size: 12px; color: #6c757d;'>" . ($day_counter++) . "</div>";
            }

            echo "</td>";
        }

        echo "</tr>";

        if ($current_day > $days_in_month && $week >= 4) break;
    }

    echo "</tbody></table>";
    echo "</div>";
}

function get_meetings_for_date($year, $month, $day, $all_meetings)
{
    $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $meetings_for_day = array();

    foreach ($all_meetings as $meeting) {
        $meeting_date = date('Y-m-d', strtotime($meeting['start_date']));
        if ($meeting_date == $date_str) {
            $meetings_for_day[] = $meeting;
        }
    }

    return $meetings_for_day;
}

function get_meeting_status_color($status)
{
    $colors = array(
        'planned' => '#ffc107',
        'confirmed' => '#28a745',
        'in_progress' => '#007bff',
        'completed' => '#6c757d',
        'cancelled' => '#dc3545',
        'postponed' => '#fd7e14'
    );

    return isset($colors[$status]) ? $colors[$status] : '#6c757d';
}

//--------------------------------------------------------------------------------------------

// Get current month/year or from form
$current_year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
$current_month = isset($_POST['month']) ? (int)$_POST['month'] : date('n');

// Get meetings for the current month
$start_date = date('Y-m-d', mktime(0, 0, 0, $current_month, 1, $current_year));
$end_date = date('Y-m-d', mktime(23, 59, 59, $current_month + 1, 0, $current_year));

$meetings = get_meetings(array(
    'start_date' => $start_date,
    'end_date' => $end_date
));

// Convert to array for easier processing
$meetings_array = array();
while ($meeting = db_fetch($meetings)) {
    $meetings_array[] = $meeting;
}

//--------------------------------------------------------------------------------------------

start_form();

hidden('year', $current_year);
hidden('month', $current_month);

display_note(_("CRM Calendar"), 0, 1);

br();

display_calendar($current_year, $current_month, $meetings_array);

br();

// Quick actions
echo "<div class='calendar-actions' style='text-align: center; margin-top: 20px;'>";
echo "<a href='/modules/CRM/pages/meetings.php' class='btn btn-primary'>" . _("Manage Meetings") . "</a> ";
echo "<a href='/modules/CRM/pages/meeting_rooms.php' class='btn btn-secondary'>" . _("Manage Rooms") . "</a> ";
echo "<a href='/modules/CRM/pages/dashboard.php' class='btn btn-info'>" . _("CRM Dashboard") . "</a>";
echo "</div>";

end_form();

end_page();
?>