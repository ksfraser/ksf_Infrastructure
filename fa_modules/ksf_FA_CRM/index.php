<?php
/**
 * ksf_FA_CRM Entry Point
 *
 * App-shell router: resolves the ?view= tab from the CrmAppShell, sets the
 * per-view security BEFORE session.inc, then boots the shell (fires the
 * `crm_register_tabs` register-with-me hook so other modules can add tabs),
 * renders the sub-menu and dispatches to the tab controller SRP or page script.
 *
 * PHP 7.3 compatible — no PHP 8+ syntax.
 *
 * @package ksf_FA_CRM
 * @since 1.0.0
 */

chdir(__DIR__);

if (file_exists(__DIR__ . '/bootstrap.php')) {
    require_once __DIR__ . '/bootstrap.php';
}

$path_to_root = "../..";

$appShell = new \Ksfraser\FA\CRM\App\CrmAppShell();

$view = isset($_GET['view']) ? (string) $_GET['view'] : $appShell->getDefaultView();
if ($appShell->getTab($view) === null) {
    $view = $appShell->getDefaultView();
}

$page_security = $appShell->getSecurity($view, 'SA_CRM_DASHBOARD');
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

// FA convention: pages include ui.inc themselves (main.inc only loads
// ui_controls). Required for the FA-native UI-lists helpers (e.g. the
// customer_list_row filter combo on the Contacts tab).
include_once($path_to_root . "/includes/ui.inc");

// Fire the register-with-me hook: other modules may add their tabs now.
$appShell->boot();

$js = '';
if (function_exists('user_use_date_picker') && user_use_date_picker()) {
    $js .= get_js_date_picker();
}

page(_("CRM"), false, false, '', $js);

echo $appShell->renderMenu($view);

// SRP dispatch: every tab is an AbstractTabController SRP (inherits the
// FA-round-trip fix: `'ajax' => false` guarantees the Save spinner → native
// POST → full-page PRG; no ajaxsubmit warning-triangle round-trip).
$appShell->dispatch($view);

end_page();
