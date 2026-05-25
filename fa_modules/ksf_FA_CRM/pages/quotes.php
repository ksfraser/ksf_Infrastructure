<?php
$page_security = 'SA_CRM';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

page(_($help_context = "CRM Quotes"));

simple_page_mode(true);

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM')
{
    $input_error = 0;

    if (strlen($_POST['quote_no']) == 0)
    {
        $input_error = 1;
        display_error(_("The quote number cannot be empty."));
        set_focus('quote_no');
    }

    if ($input_error != 1)
    {
        $quote_data = array(
            'quote_no' => $_POST['quote_no'],
            'opportunity_id' => $_POST['opportunity_id'],
            'debtor_no' => $_POST['debtor_no'],
            'contact_id' => $_POST['contact_id'],
            'quote_date' => $_POST['quote_date'],
            'valid_until' => $_POST['valid_until'],
            'status' => $_POST['status'],
            'subtotal' => $_POST['subtotal'],
            'tax_rate' => $_POST['tax_rate'],
            'tax_amount' => $_POST['tax_amount'],
            'total' => $_POST['total'],
            'notes' => $_POST['notes'],
            'terms' => $_POST['terms'],
            'created_by' => $_SESSION['wa_current_user']->name,
        );

        if ($selected_id != -1)
        {
            update_quote($selected_id, $quote_data);
            
            // Add/update line items
            if (isset($_POST['line_items'])) {
                $items = json_decode(stripslashes($_POST['line_items']), true);
                foreach ($items as $item) {
                    $item['quote_id'] = $selected_id;
                    add_quote_item($selected_id, $item);
                }
            }
            
            display_notification(_('Quote has been updated'));
        }
        else
        {
            $new_id = add_quote($quote_data);
            
            // Add line items
            if (isset($_POST['line_items'])) {
                $items = json_decode(stripslashes($_POST['line_items']), true);
                foreach ($items as $item) {
                    $item['quote_id'] = $new_id;
                    add_quote_item($new_id, $item);
                }
            }
            
            display_notification(_('Quote has been added'));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'Delete')
{
    delete_quote($selected_id);
    display_notification(_('Quote has been deleted'));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM')
{
    $myrow = get_quote($selected_id);
    if ($myrow)
    {
        $_POST['quote_no'] = $myrow['quote_no'];
        $_POST['opportunity_id'] = $myrow['opportunity_id'];
        $_POST['debtor_no'] = $myrow['debtor_no'];
        $_POST['contact_id'] = $myrow['contact_id'];
        $_POST['quote_date'] = $myrow['quote_date'];
        $_POST['valid_until'] = $myrow['valid_until'];
        $_POST['status'] = $myrow['status'];
        $_POST['subtotal'] = $myrow['subtotal'];
        $_POST['tax_rate'] = $myrow['tax_rate'];
        $_POST['tax_amount'] = $myrow['tax_amount'];
        $_POST['total'] = $myrow['total'];
        $_POST['notes'] = $myrow['notes'];
        $_POST['terms'] = $myrow['terms'];
    }
}

if ($Mode == 'RESET')
{
    $next_no = "Q-" . date('Ymd') . "-" . str_pad(db_num_rows(db_query("SELECT id FROM " . TB_PREF . "fa_crm_quotes")) + 1, 4, '0', STR_PAD_LEFT);
    $_POST['quote_no'] = $next_no;
    $_POST['opportunity_id'] = '';
    $_POST['debtor_no'] = '';
    $_POST['contact_id'] = '';
    $_POST['quote_date'] = date('Y-m-d');
    $_POST['valid_until'] = date('Y-m-d', strtotime('+30 days'));
    $_POST['status'] = 'draft';
    $_POST['subtotal'] = '0';
    $_POST['tax_rate'] = '0';
    $_POST['tax_amount'] = '0';
    $_POST['total'] = '0';
    $_POST['notes'] = '';
    $_POST['terms'] = '';
    $_POST['line_items'] = '[]';
}

//-----------------------------------------------------------------------------------

$quote_statuses = array(
    'draft' => _('Draft'),
    'sent' => _('Sent'),
    'approved' => _('Approved'),
    'rejected' => _('Rejected'),
    'accepted' => _('Accepted'),
    'expired' => _('Expired'),
);

start_form();

start_table(TABLESTYLE, "width=60%");

$heading = $Mode == 'EDIT_ITEM' ? _("Edit Quote") : _("New Quote");
table_section_title($heading);

// Quote Number
text_row_ex(_("Quote Number:"), 'quote_no', 20, '', '', '', '');

// Opportunity
opportunity_row(_("Opportunity:"), 'opportunity_id', $_POST['opportunity_id']);

// Customer
 debtor_row(_("Customer:"), 'debtor_no', $_POST['debtor_no'], true);

// Contact
contact_row(_("Contact:"), 'contact_id', $_POST['contact_id'], $_POST['debtor_no']);

// Quote Date
date_row(_("Quote Date:"), 'quote_date');

// Valid Until
date_row(_("Valid Until:"), 'valid_until');

// Status
select_row(_("Status:"), 'status', $_POST['status'], $quote_statuses);

// Subtotal
amount_row(_("Subtotal:"), 'subtotal');

// Tax Rate %
percent_row(_("Tax Rate:"), 'tax_rate');

// Tax Amount
amount_row(_("Tax Amount:"), 'tax_amount');

// Total
amount_row(_("Total:"), 'total');

// Notes
textarea_row(_("Notes:"), 'notes', $_POST['notes'], 30, 4);

// Terms
textarea_row(_("Terms:"), 'terms', $_POST['terms'], 30, 3);

// Hidden for line items (handled via JS)
hidden('line_items', '');

end_table();

submit_center($Mode == 'EDIT_ITEM' ? _("Update Quote") : _("Add Quote"), true, '', true);

//--------------------------------------------------------------------------------

$sql = "SELECT q.id, q.quote_no, q.quote_date, q.valid_until, q.status, q.total, d.name as customer_name, o.opportunity_name
    FROM " . TB_PREF . "fa_crm_quotes q
    LEFT JOIN " . TB_PREF . "debtors_master d ON q.debtor_no = d.debtor_no
    LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON q.opportunity_id = o.id
    ORDER BY q.created_at DESC";

$result = db_query($sql, "Could not get quotes");

start_table(TABLESTYLE, "width=60%");

table_header(array(
    _("ID"),
    _("Quote #"),
    _("Customer"),
    _("Opportunity"),
    _("Date"),
    _("Valid"),
    _("Status"),
    _("Total"),
    _("Actions"),
));

while ($row = db_fetch_assoc($result))
{
    $statustxt = _($row['status']);
    
    href_js_edit_link("?selected_id=" . $row['id'] . "&amp;Mode=EDIT_ITEM", 'edit', _("Edit"));
    delete_button_center("?selected_id=" . $row['id'] . "&amp;Mode=Delete", _("Delete"));

    end_row();
}

end_table();

end_form();

end_page();