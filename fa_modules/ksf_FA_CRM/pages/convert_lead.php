<?php
$page_security = 'SA_CRM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");

$lead_id = isset($_GET['lead_id']) ? $_GET['lead_id'] : 0;

if ($lead_id == 0)
{
    die("Invalid lead ID");
}

$lead = get_lead($lead_id);
if (!$lead)
{
    die("Lead not found");
}

$errors = '';

if (isset($_POST['convert_lead']))
{
    $convert_type = $_POST['convert_type'];
    $new_debtor_no = $_POST['new_debtor_no'];
    $existing_debtor_no = $_POST['existing_debtor_no'];

    if ($convert_type == 'new' && empty($new_debtor_no))
    {
        $errors = _("Please enter a new account name");
    }
    elseif ($convert_type == 'existing' && empty($existing_debtor_no))
    {
        $errors = _("Please select an existing account");
    }

    if (empty($errors))
    {
        $target_debtor = $convert_type == 'new' ? $new_debtor_no : $existing_debtor_no;

        // Convert lead
        convert_lead($lead_id, $target_debtor);

        display_notification(_("Lead converted successfully"));
        echo "<script>
            window.opener.location.reload();
            window.close();
        </script>";
    }
}

start_form();

start_table(TABLESTYLE, "width=95%");

table_section_title(_("Convert Lead"));

label_row(_("Lead:"), $lead['debtor_name'] . " (" . $lead['lead_source'] . ")");

if (!empty($errors))
{
    label_row("<span class='error'>" . _("Error:") . "</span>", $errors);
}

$convert_options = array(
    'new' => _("Create New Account"),
    'existing' => _("Use Existing Account"),
);
radiobuttons(_("Conversion Type:"), 'convert_type', 'new', $convert_options);

 debtor_row(_("New Account:"), 'new_debtor_no', '', false, false);

 debtor_row(_("Existing Account:"), 'existing_debtor_no', '', true, false);

textarea_row(_("Notes:"), 'notes', '', 30, 4);

end_table();

submit_center("convert_lead", _("Convert Lead"), true, '', false);

end_form();

end_page();