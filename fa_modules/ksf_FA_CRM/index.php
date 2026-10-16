<?php
/**
 * ksf_FA_CRM — single front controller for the whole application.
 *
 * This file is the whole preamble. Everything it used to do by hand — resolving
 * the tab from `view`, resolving the per-tab security area, booting the shell so
 * `crm_register_tabs` can contribute tabs, rendering the sub-menu, enforcing
 * access and dispatching to the tab controller SRP (or to the dashboard page
 * script) — now lives in
 * `ksfraser\FrontAccounting\Common\App\AbstractFrontController` (package
 * `ksfraser/ksf-fa-common`), driven by
 * `ksfraser\FrontAccounting\CRM\App\CrmFrontController`. What is left here is
 * the two-phase bootstrap FA forces on a page: phase A needs the include at
 * GLOBAL scope, phase B is the controller's `run()`.
 *
 * URL shape
 * ---------
 *   index.php                                default tab (dashboard)
 *   index.php?view=<tab>                     read a tab (canonical)
 *   index.php?tab=<tab>                      the same tab, house-standard alias
 *   index.php?view=<tab>&page=3              tab + pager position
 *   index.php?view=<tab>&action=save         POST only; the verb is the tab
 *                                            controller's own (see
 *                                            CrmFrontController::dispatchAction)
 *
 * `view` stays the canonical parameter for CRM: the in-page sub-menu
 * (CrmAppShell → FAModuleMenu), AdminHubTabController and the RBAC
 * application menu in hooks.php all mint `?view=`, and the tab controllers keep
 * `view` in their pager links. `tab` is accepted as an alias so the house
 * convention resolves too. `page` is PAGINATION and is never read as a tab
 * selector.
 *
 * The app id is `crm`, so boot() fires `hook_invoke_all('crm_register_tabs')`
 * and another module can add its own tab to the CRM menu.
 *
 * `session.inc` is deliberately NOT inside run(): it reads `$page_security` and
 * `$path_to_root` as GLOBALS in the including scope, so from inside a method it
 * would see neither. Hence phase A here, phase B below.
 *
 * This file is TWO levels below the FA root, which is why $path_to_root is
 * '../..' — unlike pages/*.php, which are three levels down.
 *
 * PHP 7.3 compatible.
 *
 * @package ksf_FA_CRM
 * @since   1.1.0
 *
 * @UML Note: APP_TAB_ARCHITECTURE.md §11 (host page = thin router)
 * @BABOK Related: FR-CRM-001 (app shell)
 */

chdir(__DIR__);

if (file_exists(__DIR__ . '/bootstrap.php')) {
    require_once __DIR__ . '/bootstrap.php';
}

$path_to_root = "../..";

// The fallback security area is a REQUIRED constructor argument and is never
// empty: it is what makes access enforcement fail closed for a tab contributed
// by another module that registers without an area of its own. CrmFrontController
// supplies CRM's own URL options (`view` canonical, `tab` alias, no $_GET
// normalisation, ui.inc already loaded below).
$frontController = new \ksfraser\FrontAccounting\CRM\App\CrmFrontController(
    new \ksfraser\FrontAccounting\CRM\App\CrmAppShell(),
    'SA_CRM_DASHBOARD',
    $path_to_root
);

// Phase A (global scope): the area must be known BEFORE session.inc, and it can
// only come from the tabs registered at shell-construction time — the
// crm_register_tabs hook fires on boot(), which needs a session. A tab
// contributed by another module therefore opens the session under the fallback
// area, and run() re-checks it against its OWN area once boot() has run.
$page_security = $frontController->getSecurityArea();
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();

// FA's bootstrap loads only ui_view/ui_controls/ui_msgs. ui_input.inc and
// ui_lists.inc must be pulled in by the page, or every helper the tab
// controllers use — text_row(), amount_row(), date_row(),
// array_selector_row(), submit_row(), the customer_list_row filter combo — is
// an undefined function.
//
// This include has to happen HERE, at global scope, and CrmFrontController
// therefore turns the base class's own load_ui_includes step off. includes/ui.inc
// reads `$path_to_root` as a global in its including scope; it is included here,
// at global scope, where the value is correct.
include_once($path_to_root . "/includes/ui.inc");

// Phase B: boot() + re-resolve, page and menu render, the single access check,
// then dispatch.
$frontController->run();