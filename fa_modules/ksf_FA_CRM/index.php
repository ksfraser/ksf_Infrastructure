<?php
/**
 * ksf_FA_CRM entry point
 *
 * Boots FA and dispatches to the requested view via index.php?view=<viewname>.
 */
$path_to_root = "../..";
$page_security = 'SA_CRM_DASHBOARD';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

$validViews = array(
    'dashboard'      => array('file' => 'pages/dashboard.php',      'security' => 'SA_CRM_DASHBOARD'),
    'contacts'       => array('file' => 'pages/contacts.php',       'security' => 'SA_CRM_CUSTOMER'),
    'customers'      => array('file' => 'pages/customers.php',      'security' => 'SA_CRM_CUSTOMER'),
    'leads'          => array('file' => 'pages/leads.php',          'security' => 'SA_CRM_LEAD'),
    'opportunities'  => array('file' => 'pages/opportunities.php',  'security' => 'SA_CRM_OPPORTUNITY'),
    'communications' => array('file' => 'pages/communications.php', 'security' => 'SA_CRM_COMMUNICATION'),
    'meetings'       => array('file' => 'pages/meetings.php',       'security' => 'SA_CRM_MEETING'),
    'quotes'         => array('file' => 'pages/quotes.php',         'security' => 'SA_CRM_QUOTE'),
    'customer_types' => array('file' => 'pages/customer_types.php', 'security' => 'SA_CUSTOMER_TYPE'),
    'territories'    => array('file' => 'pages/territories.php',    'security' => 'SA_TERRITORY'),
    'tags'           => array('file' => 'pages/tags.php',           'security' => 'SA_CRM_TAGS'),
    'email_accounts' => array('file' => 'pages/email_accounts.php', 'security' => 'SA_CRM_EMAIL_ACCOUNT'),
    'gedcom_import'  => array('file' => 'pages/gedcom_import.php',  'security' => 'SA_CRM_GEDCOM'),
    'gedcom_export'  => array('file' => 'pages/gedcom_export.php',  'security' => 'SA_CRM_GEDCOM'),
);

if (!isset($validViews[$view])) {
    $view = 'dashboard';
}

$page_security = $validViews[$view]['security'];

$famodulemenuPath = dirname(__DIR__) . '/ksf_FA_Common/src/Menu/FAModuleMenu.php';
if (file_exists($famodulemenuPath)) {
    require_once $famodulemenuPath;
}

$menu = new \ksfraser\FrontAccounting\Common\Menu\FAModuleMenu(
    'index.php',
    'view',
    $view
);

$menu->addItem('dashboard',      _("&Dashboard"),       null)
     ->addItem('contacts',       _("Contacts"),        null)
     ->addItem('customers',      _("Customers"),       null)
     ->addItem('leads',          _("Leads"),           null)
     ->addItem('opportunities',  _("Opportunities"),   null)
     ->addItem('communications', _("Communications"),  null)
     ->addItem('meetings',       _("Meetings"),        null)
     ->addItem('quotes',         _("Quotes"),          null)
     ->addItem('customer_types', _("Customer Types"),  null)
     ->addItem('territories',    _("Territories"),     null)
     ->addItem('tags',           _("Tags"),            null)
     ->addItem('email_accounts', _("Email Accounts"),  null);

page(_("CRM"), false, false, '', '');

echo $menu->render();

$page_dir = __DIR__ . '/pages/' . basename($view) . '.php';
if (file_exists($page_dir)) {
    include $page_dir;
} else {
    display_error(_("Unknown CRM view: ") . htmlspecialchars($view));
}

end_page();
