<?php
/**
 * GEDCOM Import Page
 *
 * Upload a GEDCOM 5.5 file and import persons, relationships,
 * life events, and roles into the CRM.
 *
 * @package ksf_FA_CRM
 * @since 1.0.0
 */

$page_security = 'SA_CRM_GEDCOM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/vendor/autoload.php");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_relationships_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/gedcom_import.php");

page(_($help_context = "GEDCOM Import"));

//--------------------------------------------------------------------------------------------

if (isset($_POST['import_gedcom']) && check_file_upload('gedcom_file')) {
    $tmp_name = $_FILES['gedcom_file']['tmp_name'];
    $orig_name = $_FILES['gedcom_file']['name'];

    if (is_uploaded_file($tmp_name)) {
        $content = file_get_contents($tmp_name);
        if ($content !== false && strlen($content) > 0) {
            $result = import_gedcom_content($content);

            display_notification(_("Import completed:"));

            start_table(TABLESTYLE);
            table_header(array(_("Metric"), _("Count")));

            start_row();
            label_cell(_("Individuals imported"));
            label_cell($result->getIndividuals());
            end_row();

            start_row();
            label_cell(_("Families imported"));
            label_cell($result->getFamilies());
            end_row();

            start_row();
            label_cell(_("Life events created"));
            label_cell($result->getLifeEvents());
            end_row();

            start_row();
            label_cell(_("Relationships created"));
            label_cell($result->getRelationships());
            end_row();

            start_row();
            label_cell(_("Roles created"));
            label_cell($result->getRoles());
            end_row();

            end_table();

            if (!empty($result->getErrors())) {
                display_error(_("Errors during import:"));
                foreach ($result->getErrors() as $error) {
                    display_error($error);
                }
            }
        } else {
            display_error(_("The uploaded file is empty or could not be read."));
        }
    } else {
        display_error(_("File upload failed."));
    }
}

//--------------------------------------------------------------------------------------------

start_form(true);

echo '<center>';

start_table(TABLESTYLE2);

file_row(_("GEDCOM File (.ged):"), 'gedcom_file', 'ged');

end_table(1);

echo '<br>';
submit_center('import_gedcom', _("Import GEDCOM"), '', 'default');

echo '</center>';

end_form();

end_page();
