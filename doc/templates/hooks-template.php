<?php
/**
 * KSF FrontAccounting Module Hooks — TEMPLATE
 *
 * Copy this file into your FA module as hooks.php and rename the class.
 * This template covers every standard FA module pattern plus the
 * KSF inter-module query hook system.
 *
 * STANDARD FA HOOKS:
 *   install_tabs()      — add a new top-level FA application tab
 *   install_options()   — add menu items under existing tabs
 *   install_access()    — register security sections and areas
 *   activate_extension()— run SQL install scripts on module activation
 *
 * KSF QUERY HOOK SYSTEM:
 *   ksf_get_value()     — respond to single-value queries from other modules
 *   ksf_get_values()    — respond to multi-value queries from other modules
 *   ksf_set_value()     — receive a value pushed from another module
 *
 * @package   ksf_FA_<ModuleName>
 * @version   1.0.0
 */

// ---------------------------------------------------------------------------
// 0. Bootstrap — Composer autoloader + dependency self-healing
// ---------------------------------------------------------------------------
// if (file_exists(__DIR__ . '/vendor/autoload.php')) {
//     require_once __DIR__ . '/vendor/autoload.php';
// }
// $depsPath = dirname(__DIR__) . '/ksf_FA_Common/src/Utils/ComposerDependencies.php';
// if (file_exists($depsPath)) {
//     require_once $depsPath;
//     \KsfCommon\Utils\ComposerDependencies::ensure(__DIR__);
// }
//
// (Uncomment in your actual hooks.php — see ksf_FA_Calendar/hooks.php for a
//  working example.  \KsfCommon\Utils\ComposerDependencies is a single SRP
//  class that runs `composer install` once if vendor/autoload.php is missing.)

// ---------------------------------------------------------------------------
// 1. Security section constant (unique per module)
// ---------------------------------------------------------------------------
// Shift value: pick a unique number not used by other modules.
// Range: 100-200 for KSF modules (below 100 reserved by FA core).
define('SS_ksf_FA_<ModuleName>', <NNN> << 8);

// ---------------------------------------------------------------------------
// Ensure Composer autoloader is loaded before the class definition so that
// trait dependencies (HookQueryProviderTrait, CrudEventEmitterTrait, etc.)
// are available at class-load time.
// ---------------------------------------------------------------------------
$moduleAutoload = dirname(__FILE__) . '/vendor/autoload.php';
if (file_exists($moduleAutoload)) {
    require_once $moduleAutoload;
}

if (!defined('SS_ksf_FA_<ModuleName>')) {
    define('SS_ksf_FA_<ModuleName>', 115 << 8);
}

if (!defined('SA_<MODULENAME>VIEW')) {
    define('SA_<MODULENAME>VIEW', SS_ksf_FA_<ModuleName> | 1);
}
if (!defined('SA_<MODULENAME>MANAGE')) {
    define('SA_<MODULENAME>MANAGE', SS_ksf_FA_<ModuleName> | 1);
}



// ---------------------------------------------------------------------------
// 2. Main hooks class
// ---------------------------------------------------------------------------

class hooks_ksf_FA_<ModuleName> extends hooks
{
    // Provides ksf_get_value(), ksf_get_values(), ksf_set_value()
    // via Ksfraser\Traits\HookQueryProviderTrait (ksfraser/traits ^1.2)
    use \Ksfraser\Traits\HookQueryProviderTrait;

    var $module_name = 'ksf_FA_<ModuleName>';
    var $version     = '1.0.0';

    // =======================================================================
    // 2a. install_tabs — Add a new top-level FA application tab
    //
    // Only override if your module adds a new tab to the FA navigation bar.
    // For most modules that add menu items to existing tabs, use
    // install_options() instead and leave this as a no-op.
    // =======================================================================
    function install_tabs($app)
    {
        // Example: add a new application tab
        // set_ext_domain('modules/ksf_FA_<ModuleName>');
        // $app->add_application(new <ModuleName>_app());
        // set_ext_domain();
    }

    // =======================================================================
    // 2b. install_options — Add menu items to existing FA app tabs
    //
    // Use switch($app->id) to target existing apps ('CRM', 'HR', 'Projects',
    // 'GL', 'AP', 'AR', 'Stock', 'Manufacturing', 'System', etc.).
    //
    //    Native FA apps: GL, system, stock, AP, orders, stock
    //
    // =======================================================================
    function install_options($app)
    {
        global $path_to_root;

        switch ($app->id) {
            // case 'CRM':
            //     $app->add_lapp_function(0, _("My Feature"),
            //         $path_to_root . "/modules/" . $this->module_name . "/page.php",
            //         'SA_<MODULENAME>VIEW', MENU_ENTRY);
            //     break;
        }
    }

    // =======================================================================
    // 2c. install_access — Register security sections and areas
    //
    // Every module page needs a corresponding SA_ constant.
    // SA_<MODULENAME>VIEW   — read access
    // SA_<MODULENAME>MANAGE — write access
    // =======================================================================
    function install_access()
    {
        $security_sections[SS_ksf_FA_<ModuleName>] = _("<Module Display Name>");

        $security_areas['SA_<MODULENAME>VIEW'] = array(
            SS_ksf_FA_<ModuleName> | 1,
            _("View <Module Display Name>")
        );
        $security_areas['SA_<MODULENAME>MANAGE'] = array(
            SS_ksf_FA_<ModuleName> | 2,
            _("Manage <Module Display Name>")
        );

        return array($security_areas, $security_sections);
    }

    // =======================================================================
    // 2d. activate_extension — Called on module install/upgrade
    //
    // Runs SQL install scripts from the sql/ subdirectory.
    // Uses @TB_PREF@ placeholder — FA's update_databases() substitutes it.
    // =======================================================================
    function activate_extension($company, $check_only = true)
    {
        if (!$check_only) {
            $this->register_hooks();
        }
        $updates = array();

        if (file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            $updates['install.sql'] = array($this->module_name);
        }

        if (file_exists(dirname(__FILE__) . '/sql/update.sql')) {
            $updates['update.sql'] = array($this->module_name);
        }

        $sqlDir = __DIR__ . '/sql';
        //Add additional sql files here
        $files = array(
            'install.sql',
            'update.sql',
            'preseed.sql'
        );
        foreach ($files as $file) {
            if (file_exists($sqlDir . '/' . $file)) {
                $updates[$file] = array($this->module_name);
            }
        }
        if (!empty($updates)) {
            return $this->update_databases($company, $updates, $check_only);
        }

        return true;
    }
    public function deactivate()
    {
        unset($GLOBALS['<module>_services_cache']);
        $GLOBALS['<module>_tab_registry'] = null;
        return true;
    }
    public function register_hooks()
    {
        // FA hook_invoke_all() calls hook methods directly on this class.
    }

    // =======================================================================
    // 3a. predefined hook functions
    //
    //
    //
    //
    // =======================================================================
    function db_prewrite( &$cart, $trans_type )
    {
    }
    public function post_item_write($itemData, $stockId = '')
    {
    }
    public function pre_item_delete($stockId = '')
    {
    }
    // =======================================================================
    // 3b. KSF QUERY HOOK SYSTEM + CRUD EVENT LISTENER STUBS
    //
    // Provided by Ksfraser\Traits\HookQueryProviderTrait (see the `use` statement
    // at the top of this class). The trait implements the standard FA hook
    // methods for inter-module value queries:
    //
    //   ksf_get_value(&$key, $opts = null)   — responds to single-value queries
    //   ksf_get_values(&$keys, $opts = null)  — responds to multi-value queries
    //   ksf_set_value(&$data, $opts = null)   — receives pushed values (no-op by default)
    //
    // To customise ksf_set_value(), override it here. Otherwise the trait's
    // default no-op implementation is used.
    //
    // Consumers call these via FA's hook_invoke_first() / hook_invoke_all().
    // IMPORTANT: FA declares &$data (by-reference). Always pass a variable:
    //
    //   $key   = '<module>.<key>';
    //   $value = hook_invoke_first('ksf_get_value', $key);
    //
    // CRUD events: When another module creates/updates/deletes a record, it
    // dispatches via CrudEventEmitterTrait (ksfraser/traits). Listen by
    // implementing a method named after the hook:
    //
    //   calendar_created_entry(&$payload, $opts = null)  — specific listener
    //   ksf_crud_event(&$payload, $opts = null)           — generic broadcast
    //
    // See: hooks-template.php sections below for commented stubs
    // =======================================================================

    // -----------------------------------------------------------------------
    // CRUD Event — react to calendar entry creation (example)
    // Uncomment and customise:
    // -----------------------------------------------------------------------
    // function calendar_created_entry(&$payload, $opts = null)
    // {
    //     $entryId = $payload['record_id'];
    //     $data    = $payload['data'];
    //     // React to calendar entry creation
    // }

    // -----------------------------------------------------------------------
    // CRUD Event — react to CRM customer update (example)
    // -----------------------------------------------------------------------
    // function crm_updated_customer(&$payload, $opts = null)
    // {
    //     $customerId = $payload['record_id'];
    //     $changed    = $payload['data']['changed_fields'] ?? array();
    //     // React to customer update
    // }

    // -----------------------------------------------------------------------
    // CRUD Event — generic catch-all listener
    // -----------------------------------------------------------------------
    // function ksf_crud_event(&$payload, $opts = null)
    // {
    //     switch ($payload['action']) {
    //         case 'created':
    //             // $payload['module'], $payload['record_type'], $payload['record_id']
    //             break;
    //         case 'updated':
    //             break;
    //         case 'deleted':
    //             // Clean up related data when a record is deleted
    //             break;
    //     }
    // }

    // -----------------------------------------------------------------------
    // 3c Tabs on our app
    // -----------------------------------------------------------------------

    public function display_tab_headers($tabs, $index = '')
    {
        if ($this->can_check_access() && !$this->has_<module>_access()) {
            return $tabs;
        }

        $resolvedindex = $index;
        if ($resolvedindex === '' && isset($_POST['<module>_id'])) {
            $resolvedindex = (string)$_POST['<module>_id'];
        }

        if (is_object($tabs) && method_exists($tabs, 'createTab')) {
            foreach ($this->get_tab_registry()->getAvailableTabs((string)$resolvedindex) as $tab) {
                $tabs->createTab($tab->getTabKey(), $tab->getTabLabel());
            }
            return $tabs;
        }

        if (!is_array($tabs)) {
            return $tabs;
        }

        foreach ($this->get_tab_registry()->getAvailableTabs((string)$resolvedindex) as $tab) {
            $tabs[$tab->getTabKey()] = array(
                $tab->getTabLabel(),
                $resolvedindex
            );
        }

        return $tabs;
    }

    public function display_tab_content($index = '', $selectedTab = '')
    {
        $this->load_plugins_on_demand();

        if ($this->can_check_access() && !$this->has_<module>_access()) {
            return false;
        }

        $resolvedindex = $index;
        if ($resolvedindex === '' && isset($_POST['<module>_id'])) {
            $resolvedindex = (string)$_POST['<module>_id'];
        }

        $tab = $this->get_tab_registry()->getTab((string)$selectedTab);
        if ($tab !== null) {
            $tab->renderTabContent((string)$resolvedindex);
            return true;
        }

        return false;
    }

//Child modules should be able to insert their tabs onto this registry because of the globals.
    private function get_tab_registry(): TabRegistry
    {
        //THIS CHECK IS FOR PARENT APP TABS
        if (isset($GLOBALS['<module>_tab_registry'])
            && $GLOBALS['<module>_tab_registry'] instanceof TabRegistry) {
            return $GLOBALS['<module>_tab_registry'];
        }

        $services = $this->get_services();
        $registry = new TabRegistry();
        //For sub modules
        //$registry = $GLOBALS['<module>_tab_registry'];

        //Add new menu tabs
        //$registry->register(new AttributesTab($services['service'], $services['handler']));
        //$registry->register(new ShippingTab($services['shipping_dao']));
        
        $GLOBALS['<module>_tab_registry'] = $registry;
        return $registry;
    }

    // =======================================================================
    // 4. Private helpers
    // =======================================================================

    /**
     * Return all values this module advertises for the query hook system.
     *
     * Each key is namespaced as "<module>.<value_name>" to prevent
     * collisions between modules. Values can be:
     *   - PHP constants (via defined() guard)
     *   - FA company preferences (via get_company_pref())
     *   - Module version strings
     *   - Configuration arrays
     *
     * @return array<string, mixed>
     */
    protected function _getAdvertisedValues(): array
    {
        return array(
            // ---- PHP constants (with defined() guard) ----
            // '<module>.api_version' => defined('<MODULE>_API_VERSION')
            //     ? constant('<MODULE>_API_VERSION') : null,

            // ---- FA company preferences ----
            // '<module>.some_pref' => function_exists('get_company_pref')
            //     ? get_company_pref('pref_name') : null,

            // ---- Metadata ----
            '<module>.version'     => $this->version,
            '<module>.module_name' => $this->module_name,
            '<module>.hooks_version' => '2.0',  // Helps consumers know which
                                                 // hook patterns are supported
        );
    }

    /**
     * Ensure Composer autoloader is available.
     *
     * Runs `composer install` on module activation if vendor/ is missing.
     * Safe to call from any method — checks file_exists first.
     *
     * @return void
     */
    private function ensure_composer_dependencies()
    {
        $module_dir    = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';

        if (file_exists($autoload_path)) {
            return;
        }

        $composer_path = $module_dir . '/composer.json';
        if (!file_exists($composer_path)) {
            return;
        }

        chdir($module_dir);
        $output      = array();
        $return_code = 0;
        exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            error_log('KSF Module: composer install failed: ' . implode("\n", $output));
        }
    }
    private function can_check_access()
    {
        return function_exists('user_check_access');
    }
    private function has_<module>_access()
    {
        global $security_areas;

        if (!isset($security_areas['SA_<MODULENAME>'])
            && !isset($security_areas['SA_<MODULENAME>VIEW'])) {
            return true;
        }

        $hasAccess = false;
        if (isset($security_areas['SA_<MODULENAME>VIEW'])) {
            $hasAccess = $hasAccess || user_check_access('SA_<MODULENAME>VIEW');
        }
        if (isset($security_areas['SA_<MODULENAME>MANAGE'])) {
            $hasAccess = $hasAccess || user_check_access('SA_<MODULENAME>MANAGE');
        }
        return $hasAccess;
    }
}

// ===========================================================================
// 5. Application class — Only needed if install_tabs() adds a new tab
// ===========================================================================
// Uncomment and customise if your module creates a new top-level FA tab.
//
// class <ModuleName>_app extends application {
//     function __construct() {
//         parent::__construct("<TabId>",
//             _($this->help_context = "&<TabLabel>"));
//
//         $this->add_module(_("<SectionName>"));
//         $this->add_lapp_function(0, _("&Page Title"),
//             "modules/ksf_FA_<ModuleName>/page.php",
//             'SA_<MODULENAME>VIEW', MENU_MAIN);
//
//         $this->add_extensions();
//     }
// }
