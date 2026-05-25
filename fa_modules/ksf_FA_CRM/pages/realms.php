<?php
$page_security = 'SA_CRMADMIN';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");

page(_($help_context = "CRM Realms"));

simple_page_mode(true);

//-----------------------------------------------------------------------------------

if ($Mode=='ADD_ITEM' || $Mode=='UPDATE_ITEM')
{
    $input_error = 0;

    if (strlen($_POST['name']) == 0)
    {
        $input_error = 1;
        display_error(_("The realm name cannot be empty."));
        set_focus('name');
    }

    if ($input_error != 1)
    {
        $realm_data = array(
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'requires_quote' => isset($_POST['requires_quote']) ? 1 : 0,
            'requires_project' => isset($_POST['requires_project']) ? 1 : 0,
            'default_stage' => $_POST['default_stage'],
            'stages_json' => $_POST['stages_json'],
            'sort_order' => $_POST['sort_order'],
        );

        if ($selected_id != -1)
        {
            update_realm($selected_id, $realm_data);
            display_notification(_('Realm has been updated'));
        }
        else
        {
            add_realm($realm_data);
            display_notification(_('Realm has been added'));
        }
        $Mode = 'RESET';
    }
}

if ($Mode == 'Delete')
{
    delete_realm($selected_id);
    display_notification(_('Realm has been deleted'));
    $Mode = 'RESET';
}

if ($Mode == 'EDIT_ITEM')
{
    $myrow = get_realm($selected_id);
    if ($myrow)
    {
        $_POST['name'] = $myrow['name'];
        $_POST['description'] = $myrow['description'];
        $_POST['requires_quote'] = $myrow['requires_quote'];
        $_POST['requires_project'] = $myrow['requires_project'];
        $_POST['default_stage'] = $myrow['default_stage'];
        $_POST['stages_json'] = $myrow['stages_json'];
        $_POST['sort_order'] = $myrow['sort_order'];
    }
}

if ($Mode == 'RESET')
{
    $_POST['name'] = '';
    $_POST['description'] = '';
    $_POST['requires_quote'] = 0;
    $_POST['requires_project'] = 0;
    $_POST['default_stage'] = 'qualification';
    $_POST['stages_json'] = '';
    $_POST['sort_order'] = 0;
}

//-----------------------------------------------------------------------------------

$default_stages = array(
    'qualification' => _('Qualification'),
    'discovery' => _('Discovery'),
    'proposal' => _('Proposal'),
    'negotiation' => _('Negotiation'),
    'closed_won' => _('Closed Won'),
    'closed_lost' => _('Closed Lost'),
);

start_form();

start_table(TABLESTYLE, "width=60%");

$heading = $Mode == 'EDIT_ITEM' ? _("Edit Realm") : _("New Realm");
table_section_title($heading);

// Name
text_row_ex(_("Realm Name:"), 'name', 30, '', '', '', '');

// Description
text_row_ex(_("Description:"), 'description', 50, '', '', '', '');

// Sort Order
smallint_row(_("Sort Order:"), 'sort_order', $_POST['sort_order']);

// Default Stage
select_row(_("Default Stage:"), 'default_stage', $_POST['default_stage'], $default_stages);

// Stages JSON
textarea_row(_("Custom Stages (JSON):"), 'stages_json', $_POST['stages_json'], 30, 4, _("e.g. ['stage1','stage2'] or leave empty for default"));

// Requires Quote
check_row(_("Requires Quote to Convert:"), 'requires_quote', $_POST['requires_quote']);

// Requires Project
check_row(_("Requires Project to Convert:"), 'requires_project', $_POST['requires_project']);

end_table();

submit_center($Mode == 'EDIT_ITEM' ? _("Update Realm") : _("Add Realm"), true, '', true);

//--------------------------------------------------------------------------------

$sql = "SELECT id, name, description, requires_quote, requires_project, default_stage, sort_order, inactive
    FROM " . TB_PREF . "fa_crm_realms
    ORDER BY sort_order, name";

$result = db_query($sql, "Could not get realms");

start_table(TABLESTYLE, "width=60%");

table_header(array(
    _("ID"),
    _("Name"),
    _("Description"),
    _("Quote"),
    _("Project"),
    _("Default Stage"),
    _("Order"),
    _("Actions"),
));

while ($row = db_fetch_assoc($result))
{
    $qtxt = $row['requires_quote'] ? _('Yes') : _('No');
    $ptxt = $row['requires_project'] ? _('Yes') : _('No');
    $inactive = $row['inactive'] ? _('(inactive)') : '';

    href_js_edit_link("?selected_id=" . $row['id'] . "&amp;Mode=EDIT_ITEM", 'edit', _("Edit"));
    delete_button_center("?selected_id=" . $row['id'] . "&amp;Mode=Delete", _("Delete"));

    end_row();
}

end_table();

end_form();

end_page();