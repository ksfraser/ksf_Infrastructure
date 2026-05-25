<?php
/**
 * CRM Tag Type Constants and Helper Functions
 *
 * Defines CRM-specific tag types that extend FA's existing
 * tags + tag_associations system. Uses the same 0_tags and
 * 0_tag_associations tables but with our own type constants.
 *
 * @package ksf_FA_CRM
 */

define('TAG_CUSTOMER',    3);
define('TAG_CONTACT',     4);
define('TAG_OPPORTUNITY', 5);
define('TAG_LEAD',        6);
define('TAG_COMMUNICATION', 7);

/**
 * Get CRM tag type name for display
 */
function crm_tag_type_name($type) {
    $names = array(
        TAG_CUSTOMER    => _("Customer"),
        TAG_CONTACT     => _("Contact"),
        TAG_OPPORTUNITY => _("Opportunity"),
        TAG_LEAD        => _("Lead"),
        TAG_COMMUNICATION => _("Communication"),
    );
    return $names[$type] ?? _("Unknown");
}

/**
 * Get all CRM tag types
 */
function crm_tag_types() {
    return array(TAG_CUSTOMER, TAG_CONTACT, TAG_OPPORTUNITY, TAG_LEAD, TAG_COMMUNICATION);
}

/**
 * Get tag type label for switch statements
 */
function crm_tag_type_label($type) {
    $labels = array(
        TAG_CUSTOMER    => 'customer',
        TAG_CONTACT     => 'contact',
        TAG_OPPORTUNITY => 'opportunity',
        TAG_LEAD        => 'lead',
        TAG_COMMUNICATION => 'communication',
    );
    return $labels[$type] ?? 'unknown';
}

/**
 * Get tag type from string label
 */
function crm_tag_type_from_label($label) {
    $map = array(
        'customer'    => TAG_CUSTOMER,
        'contact'     => TAG_CONTACT,
        'opportunity' => TAG_OPPORTUNITY,
        'lead'        => TAG_LEAD,
        'communication' => TAG_COMMUNICATION,
    );
    return $map[$label] ?? null;
}
