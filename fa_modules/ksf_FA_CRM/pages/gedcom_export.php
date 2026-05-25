<?php
/**
 * GEDCOM Export Page
 *
 * Export CRM persons, relationships, and life events
 * as a GEDCOM 5.5 file download.
 *
 * @package ksf_FA_CRM
 * @since 1.0.0
 */

$page_security = 'SA_CRM_GEDCOM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/data_checks.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/vendor/autoload.php");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/gedcom_import.php");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/gedcom_export.php");

page(_($help_context = "GEDCOM Export"));

//--------------------------------------------------------------------------------------------

if (isset($_POST['export_gedcom'])) {
    $export_all = isset($_POST['export_all']) ? (int)$_POST['export_all'] : 1;
    $person_id  = isset($_POST['person_id']) ? (int)$_POST['person_id'] : null;

    if (!$export_all && !$person_id) {
        display_error(_("Please select a person to export or choose 'Export All'."));
    } else {
        $gedcom = export_gedcom($export_all ? null : $person_id);

        if ($gedcom !== '') {
            $filename = 'crm_export_' . date('Ymd_His') . '.ged';

            header('Content-Type: application/x-gedcom');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($gedcom));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $gedcom;
            exit;
        } else {
            display_error(_("No data to export."));
        }
    }
}

//--------------------------------------------------------------------------------------------

start_form();

echo '<center>';

start_table(TABLESTYLE2);

check_row(_("Export All Persons:"), 'export_all', 1);

$persons = get_contact_persons_selector();
if (!empty($persons)) {
    $sel = array('' => _("Select Person"));
    foreach ($persons as $id => $name) {
        $sel[$id] = $name;
    }
    echo '<tr><td class="label">' . _("Specific Person:") . '</td><td>';
    echo array_selector('person_id', null, $sel);
    echo '</td></tr>';
}

end_table(1);

echo '<br>';
submit_center('export_gedcom', _("Export GEDCOM"), '', 'default');

echo '</center>';

end_form();

end_page();
