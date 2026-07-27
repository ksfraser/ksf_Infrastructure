<?php
/**
 * CRM sub-page router — included from FA main index.php.
 */
$path_to_root = "../..";
$page_security = 'SA_CRM_DASHBOARD';
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$page_security = 'SA_CRM_DASHBOARD';
$page_dir = dirname(__FILE__) . '/' . basename($view) . '.php';

page(_("CRM"), false, false, '', '');

if (file_exists($page_dir)) {
    include $page_dir;
} else {
    display_error(_("Unknown CRM view: ") . htmlspecialchars($view));
}

end_page();
