<?php
$page_security = 'SA_CRM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

page(_($help_context = "CRM Leads"));

simple_page_mode(true);

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM')
{
    $input_error = 0;

    if (strlen($_POST['debtor_no']) == 0)
    {
        $input_error = 1;
        display_error(_("The lead cannot be empty."));
        set_focus('debtor_no');
    }

    if ($input_error != 1)
    {
        if ($selected_id != -1)
        {
            update_lead($selected_id, $_POST);
            display_notification(_('Selected lead has been updated'));
        }
        else
        {
            add_lead($_POST);
            display_notification(_('New lead has been added'));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'Delete')
{
    delete_lead($selected_id);
    display_notification(_('Lead has been deleted'));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM')
{
    $myrow = get_lead($selected_id);
    if ($myrow)
    {
        $_POST['debtor_no'] = $myrow['debtor_no'];
        $_POST['lead_source'] = $myrow['lead_source'];
        $_POST['lead_status'] = $myrow['lead_status'];
        $_POST['rating'] = $myrow['rating'];
        $_POST['annual_revenue'] = $myrow['annual_revenue'];
        $_POST['employee_count'] = $myrow['employee_count'];
        $_POST['industry'] = $myrow['industry'];
        $_POST['website'] = $myrow['website'];
        $_POST['phone'] = $myrow['phone'];
        $_POST['email'] = $myrow['email'];
        $_POST['address'] = $myrow['address'];
        $_POST['assigned_to'] = $myrow['assigned_to'];
        $_POST['campaign_id'] = $myrow['campaign_id'];
        $_POST['notes'] = $myrow['notes'];
    }
}

if ($Mode == 'RESET')
{
    $_POST['debtor_no'] = '';
    $_POST['lead_source'] = '';
    $_POST['lead_status'] = 'new';
    $_POST['rating'] = '';
    $_POST['annual_revenue'] = '';
    $_POST['employee_count'] = '';
    $_POST['industry'] = '';
    $_POST['website'] = '';
    $_POST['phone'] = '';
    $_POST['email'] = '';
    $_POST['address'] = '';
    $_POST['assigned_to'] = '';
    $_POST['campaign_id'] = '';
    $_POST['notes'] = '';
}

//-----------------------------------------------------------------------------------
// Lead Status values
$lead_statuses = array(
    'new' => _('New'),
    'assigned' => _('Assigned'),
    'in_progress' => _('In Progress'),
    'converted' => _('Converted'),
    'dead' => _('Dead'),
    ' Recycled' => _('Recycled'),
);

$lead_sources = array(
    '' => _('- Select Source -'),
    'web' => _('Web'),
    'referral' => _('Referral'),
    'cold_call' => _('Cold Call'),
    'print' => _('Print'),
    'trade_show' => _('Trade Show'),
    'seminar' => _('Seminar'),
    'campaign' => _('Campaign'),
    'other' => _('Other'),
);

$ratings = array(
    '' => _('- No Rating -'),
    'hot' => _('Hot'),
    'warm' => _('Warm'),
    'cold' => _('Cold'),
);

start_form();

start_table(TABLESTYLE, "width=60%");

$heading = $Mode == 'EDIT_ITEM' ? _("Edit Lead") : _("New Lead");
table_section_title($heading);

// Customer (Debtor) for lead
 debtor_row(_("Account:"), 'debtor_no', $_POST['debtor_no'], true);

// Lead Status
label_row(_("Status:"), $_POST['lead_status']);
hidden('lead_status', $_POST['lead_status']);

// Lead Source
text_row_ex(_("Lead Source:"), 'lead_source', 30, '', '', '', '');

// Rating
select_row(_("Rating:"), 'rating', $_POST['rating'], $ratings);

// Annual Revenue
amount_row(_("Annual Revenue:"), 'annual_revenue');

// Employee Count
smallint_row(_("Employee Count:"), 'employee_count');

// Industry
text_row_ex(_("Industry:"), 'industry', 30, '', '', '', '');

// Website
text_row_ex(_("Website:"), 'website', 50, '', '', '', '');

// Phone
text_row_ex(_("Phone:"), 'phone', 20, '', '', '', '');

// Email  
text_row_ex(_("Email:"), 'email', 50, '', '', '', '');

// Address
textarea_row(_("Address:"), 'address', $_POST['address'], 30, 4);

// Assigned To
text_row_ex(_("Assigned To:"), 'assigned_to', 30, '', '', '', '');

// Notes
textarea_row(_("Notes:"), 'notes', $_POST['notes'], 30, 4);

end_table();

submit_center($Mode == 'EDIT_ITEM' ? _("Update Lead") : _("Add Lead"), true, '', true);

//--------------------------------------------------------------------------------

$sql = "SELECT l.id, l.debtor_no, d.name as customer_name, l.lead_source, l.lead_status, l.rating, l.created_at
    FROM " . TB_PREF . "fa_crm_leads l
    LEFT JOIN " . TB_PREF . "debtors_master d ON l.debtor_no = d.debtor_no
    WHERE l.converted_to_debtor_no IS NULL
    ORDER BY l.created_at DESC";

$result = db_query($sql, "Could not get leads");

start_table(TABLESTYLE, "width=60%");

table_header(array(
    _("ID"),
    _("Account"),
    _("Source"),
    _("Status"),
    _("Rating"),
    _("Created"),
    _("Actions"),
));

while ($row = db_fetch_assoc($result))
{
    $converttxt = '';
    if ($row['lead_status'] == 'converted') {
        $converttxt = " [converted]";
    }
    elseif ($row['lead_status'] == 'new') {
        $converttxt = " - <a href='$path_to_root/modules/ksf_FA_CRM/pages/convert_lead.php?lead_id=" . $row['id'] . "' onclick=\"javascript:openWindow(this.href,'convertLead','width=600,height=400');return false;\">" . _("Convert") . "</a>";
    }

    href_js_edit_link("?selected_id=" . $row['id'] . "&amp;Mode=EDIT_ITEM", 'edit', _("Edit"));
    delete_button_center("?selected_id=" . $row['id'] . "&amp;Mode=Delete", _("Delete"));

    end_row();
}

end_table();

end_form();

end_page();