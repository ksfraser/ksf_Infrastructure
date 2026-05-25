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
// 1. Security section constant (unique per module)
// ---------------------------------------------------------------------------
// Shift value: pick a unique number not used by other modules.
// Range: 100-200 for KSF modules (below 100 reserved by FA core).
define('SS_ksf_FA_<ModuleName>', <NNN> << 8);

// ---------------------------------------------------------------------------
// 2. Main hooks class
// ---------------------------------------------------------------------------

class hooks_ksf_FA_<ModuleName> extends hooks
{
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
        $updates = array();

        if (file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            $updates['install.sql'] = array($this->module_name);
        }

        if (file_exists(dirname(__FILE__) . '/sql/update.sql')) {
            $updates['update.sql'] = array($this->module_name);
        }

        if (!empty($updates)) {
            return $this->update_databases($company, $updates, $check_only);
        }

        return true;
    }

    // =======================================================================
    // 3. KSF QUERY HOOK SYSTEM
    //
    // These methods implement the inter-module value query protocol using
    // FA's native hook_invoke_first() / hook_invoke_all().
    //
    // PROBLEM: Constants and config values defined in entry-point scripts
    // (e.g. cal.php) are not available when another module's hooks.php is
    // loaded via install_hooks() — that happens early in session.inc, long
    // before any module page is reached.
    //
    // SOLUTION: A standardized hook-based registry. Modules advertise their
    // values here in hooks.php. Consumers query them at any time via:
    //
    //   $key   = '<module>.<key>';
    //   $value = hook_invoke_first('ksf_get_value', $key);
    //
    // IMPORTANT: FA's hook_invoke_first/all declare &$data (by-reference).
    // ALWAYS pass a variable, never a literal.
    //
    // BENEFITS:
    //   - No load-order dependency (hook_invoke_first finds the responder)
    //   - No direct code dependency between modules
    //   - Works regardless of which entry point initiated the request
    //   - Each value key is namespaced by module name to prevent collisions
    // =======================================================================

    // -----------------------------------------------------------------------
    // 3a. ksf_get_value — Respond to a single-value query (hook_invoke_first)
    //
    // Check if $key matches one of this module's advertised values.
    // Return the value, or null to pass the query to the next module.
    //
    // NOTE: FA calls hook methods as $hook->$method($data, $opts). The first
    // parameter ($data) is passed by reference from hook_invoke_first/all.
    // For query hooks we declare &$key by convention but only read it.
    //
    // @param mixed $key   Namespaced key: "<module>.<value_name>"
    // @param mixed $opts  Reserved (defaults to null per FA convention)
    // @return mixed|null  The value if recognized, null if not mine
    // -----------------------------------------------------------------------
    function ksf_get_value(&$key, $opts = null)
    {
        $values = $this->_get_advertised_values();

        return array_key_exists($key, $values) ? $values[$key] : null;
    }

    // -----------------------------------------------------------------------
    // 3b. ksf_get_values — Respond to a multi-value query (hook_invoke_all)
    //
    // Called when another module wants all advertised values from all modules.
    // Return an array of {key => value} pairs that belong to this module.
    //
    // @param mixed $keys  List of requested keys (null = return all)
    // @param mixed $opts  Reserved
    // @return array       Associative array of key => value pairs
    // -----------------------------------------------------------------------
    function ksf_get_values(&$keys = null, $opts = null)
    {
        $values = $this->_get_advertised_values();

        if (empty($keys)) {
            return $values; // return all
        }

        return array_intersect_key($values, array_flip($keys));
    }

    // -----------------------------------------------------------------------
    // 3c. ksf_set_value — Receive a value pushed from another module
    //
    // Called via hook_invoke_all('ksf_set_value', $payload) so every module
    // gets notified. Modules that recognise the key may store or act on it.
    // This is "fire and forget" — no return value is used.
    //
    // NOTE: $data is a compound array expected to contain 'key' and 'value'.
    // This conforms to FA's standard calling convention ($hook->$method($data, $opts)).
    //
    // Caller:
    //   $payload = ['key' => '<module>.some_setting', 'value' => '...'];
    //   hook_invoke_all('ksf_set_value', $payload);
    //
    // @param mixed $data  Compound array with 'key' and 'value' entries
    // @param mixed $opts  Reserved
    // -----------------------------------------------------------------------
    function ksf_set_value(&$data, $opts = null)
    {
        // $key   = $data['key']   ?? null;
        // $value = $data['value'] ?? null;
        // if ($key === '<module>.some_setting') {
        //     $this->_cache[$key] = $value;
        // }
    }

    // =======================================================================
    // 3d. CRUD Event Listener Stubs
    //
    // These methods react to CRUD events emitted by other modules via the
    // KSF CrudEventEmitterTrait. A module may listen for:
    //
    //   1. SPECIFIC events — method named <module>_<action>_<recordType>
    //      e.g. calendar_created_entry, crm_updated_customer
    //
    //   2. GENERIC events — method named ksf_crud_event; check $payload
    //      for action/module/record_type to decide if relevant.
    //
    // Uncomment and customise the handlers below for events your module
    // needs to react to.
    //
    // See: ksfraser/traits — Ksfraser\Traits\CrudEventEmitterTrait
    // =======================================================================

    // -----------------------------------------------------------------------
    // 3d-i. Specific listener example — react to calendar entry creation
    // -----------------------------------------------------------------------
    // function calendar_created_entry(&$payload, $opts = null)
    // {
    //     $entryId = $payload['record_id'];
    //     $data    = $payload['data'];
    //     // React to calendar entry creation
    // }

    // -----------------------------------------------------------------------
    // 3d-ii. Specific listener example — react to CRM customer update
    // -----------------------------------------------------------------------
    // function crm_updated_customer(&$payload, $opts = null)
    // {
    //     $customerId = $payload['record_id'];
    //     $changed    = $payload['data']['changed_fields'] ?? array();
    //     // React to customer update
    // }

    // -----------------------------------------------------------------------
    // 3d-iii. Generic listener — catches ALL CRUD events from any module
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
    private function _get_advertised_values()
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
