<?php
/**
 * ksf_FA_CRM Module Hooks for FrontAccounting
 *
 * CRM adapter hooks: security, menu items, and DB installation.
 *
 * @package ksf_FA_CRM
 * @version 1.0.0
 */

define('SS_CRM', 114 << 8);

require_once dirname(__FILE__) . '/includes/crm_tags.inc';

class hooks_ksf_FA_CRM extends hooks {
    use \Ksfraser\Traits\HookQueryProviderTrait;

    var $module_name = 'ksf_FA_CRM';
    var $version = '1.0.0';

    /**
     * Add menu items to existing FA applications
     *
     * @param application $app FA application instance
     */
    function install_options($app) {
        global $path_to_root;

        switch($app->id) {
            case 'sales':
                $app->add_lapp_function(0, _("CRM Dashboard"),
                    $path_to_root."/modules/".$this->module_name."/pages/dashboard.php", 'SA_CRM_DASHBOARD', MENU_MAIN);
                $app->add_lapp_function(1, _("CRM Customers"),
                    $path_to_root."/modules/".$this->module_name."/pages/customers.php", 'SA_CRM_CUSTOMER', MENU_ENTRY);
                $app->add_lapp_function(1, _("Opportunities"),
                    $path_to_root."/modules/".$this->module_name."/pages/opportunities.php", 'SA_CRM_OPPORTUNITY', MENU_ENTRY);
                $app->add_lapp_function(2, _("Communications Log"),
                    $path_to_root."/modules/".$this->module_name."/pages/communications.php", 'SA_CRM_COMMUNICATION', MENU_INQUIRY);
                $app->add_rapp_function(3, _("CRM Setup"),
                    $path_to_root."/modules/".$this->module_name."/pages/setup.php", 'SA_CRM_SETUP', MENU_MAINTENANCE);
                $app->add_rapp_function(3, _("GEDCOM Import"),
                    $path_to_root."/modules/".$this->module_name."/pages/gedcom_import.php", 'SA_CRM_GEDCOM', MENU_ENTRY);
                $app->add_rapp_function(3, _("GEDCOM Export"),
                    $path_to_root."/modules/".$this->module_name."/pages/gedcom_export.php", 'SA_CRM_GEDCOM', MENU_ENTRY);
                break;
            case 'system':
            case 'admin':
                $app->add_lapp_function(0, _("CRM Tags"),
                    $path_to_root."/modules/".$this->module_name."/pages/crm_tags.php", 'SA_CRM_TAGS', MENU_MAINTENANCE);
                break;
        }
    }

    /**
     * Define security areas and sections
     *
     * @return array [0] => $security_areas, [1] => $security_sections
     */
    function install_access() {
        $security_sections[SS_CRM] = _("CRM Management");

        $security_areas['SA_CRM_DASHBOARD'] = array(SS_CRM | 1, _("CRM Dashboard"));
        $security_areas['SA_CRM_CUSTOMER'] = array(SS_CRM | 2, _("CRM Customers"));
        $security_areas['SA_CRM_OPPORTUNITY'] = array(SS_CRM | 3, _("CRM Opportunities"));
        $security_areas['SA_CRM_COMMUNICATION'] = array(SS_CRM | 4, _("CRM Communications"));
        $security_areas['SA_CRM_SETUP'] = array(SS_CRM | 5, _("CRM Setup"));
        $security_areas['SA_CUSTOMER_TYPE'] = array(SS_CRM | 6, _("Customer Types"));
        $security_areas['SA_TERRITORY'] = array(SS_CRM | 7, _("Territories"));
        $security_areas['SA_CRM_LEAD'] = array(SS_CRM | 8, _("CRM Leads"));
        $security_areas['SA_CRM_QUOTE'] = array(SS_CRM | 9, _("CRM Quotes"));
        $security_areas['SA_CRM_REALM'] = array(SS_CRM | 10, _("CRM Realms"));
        $security_areas['SA_CRM_MEETING'] = array(SS_CRM | 11, _("CRM Meetings"));
        $security_areas['SA_CRM_EMAIL_ACCOUNT'] = array(SS_CRM | 12, _("CRM Email Accounts"));
        $security_areas['SA_CRM_TAGS'] = array(SS_CRM | 13, _("CRM Tags"));
        $security_areas['SA_CRM_GEDCOM'] = array(SS_CRM | 14, _("GEDCOM Import/Export"));

        return array($security_areas, $security_sections);
    }

    /**
     * Advertise module capabilities for other modules (RBAC, Calendar, etc.)
     *
     * @return array Namespaced key-value pairs
     */
    protected function _getAdvertisedValues(): array
    {
        return array(
            'crm.hooks_version' => '1.0',
            'crm.module_version' => '1.0.0',
            'crm.tag_types' => array(TAG_CUSTOMER, TAG_CONTACT, TAG_OPPORTUNITY, TAG_LEAD, TAG_COMMUNICATION),
            'crm.features' => array('customers', 'contacts', 'opportunities', 'communications', 'leads', 'quotes', 'tags', 'calendar'),
        );
    }

    /**
     * Activate extension - runs SQL installation
     *
     * @param int $company Company number
     * @param bool $check_only Only check if activation possible
     * @return bool Success
     */
    function activate_extension($company, $check_only=true) {
        $this->ensure_composer_dependencies();
        $updates = array('install.sql' => array($this->module_name));
        return $this->update_databases($company, $updates, $check_only);
    }

    /**
     * Install composer dependencies if vendor/ is missing
     */
    private function ensure_composer_dependencies() {
        $module_dir = dirname(__FILE__);
        $autoload_path = $module_dir . '/vendor/autoload.php';

        if (file_exists($autoload_path)) {
            return;
        }

        $composer_path = $module_dir . '/composer.json';
        if (!file_exists($composer_path)) {
            return;
        }

        chdir($module_dir);
        $output = array();
        $return_code = 0;
        exec('composer install --no-interaction --prefer-dist 2>&1', $output, $return_code);
        if ($return_code !== 0) {
            error_log('ksf_FA_CRM: composer install failed: ' . implode("\n", $output));
        }
    }
}
