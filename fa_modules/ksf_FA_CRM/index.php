<?php
/**
 * ksf_FA_CRM entry point
 *
 * Router page following the standard FA module pattern.
 * Session-expiry handling and AJAX support can be added later.
 *
 * PHP 7.3 compatible — no PHP 8+ syntax.
 *
 * @package ksf_FA_CRM
 * @since 1.0.0
 */

chdir(__DIR__);

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

$page_security = 'SA_CRM_DASHBOARD';

$path_to_root = "../..";
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

$view = isset($_GET['view']) ? (string) $_GET['view'] : 'dashboard';

$validViews = array(
    'dashboard'        => 'pages/dashboard.php',
    'contacts'         => 'pages/contact_relationships.php',
    'customers'        => 'pages/customer_types.php',
    'leads'            => 'pages/leads.php',
    'opportunities'    => 'pages/opportunities.php',
    'communications'   => 'pages/communications.php',
    'meetings'         => 'pages/meetings.php',
    'quotes'           => 'pages/quotes.php',
    'customer_types'   => 'pages/customer_types.php',
    'territories'      => 'pages/territories.php',
    'tags'             => 'pages/crm_tags.php',
    'email_accounts'   => 'pages/email_accounts.php',
);

$pageFile = isset($validViews[$view]) ? $validViews[$view] : 'pages/dashboard.php';

$page = $view;

$js = '';
if (function_exists('user_use_date_picker') && user_use_date_picker()) {
    $js .= get_js_date_picker();
}
page(_("CRM"), false, false, '', $js);

$subMenu = new \ksfraser\FrontAccounting\Common\Menu\FAModuleMenu(
    'index.php',
    'view',
    $view
);

$subMenu->addItem('dashboard',       _("&Dashboard"),       null)
        ->addItem('contacts',         _("Contacts"),         null)
        ->addItem('customers',        _("Customers"),        null)
        ->addItem('leads',            _("Leads"),            null)
        ->addItem('opportunities',    _("Opportunities"),    null)
        ->addItem('communications',   _("Communications"),   null)
        ->addItem('meetings',         _("Meetings"),         null)
        ->addItem('quotes',           _("Quotes"),           null)
        ->addItem('customer_types',   _("Customer Types"),   null)
        ->addItem('territories',      _("Territories"),      null)
        ->addItem('tags',             _("Tags"),             null)
        ->addItem('email_accounts',   _("Email Accounts"),   null);

echo $subMenu->render();

include($pageFile);

end_page();
