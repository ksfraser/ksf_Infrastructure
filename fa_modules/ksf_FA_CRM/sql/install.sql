-- ============================================================================
-- ksf_FA_CRM Module Installation SQL
-- ============================================================================
-- Uses @TB_PREF@ placeholder which is replaced by FA's update_databases()
-- ============================================================================

-- ============================================================================
-- CORE CRM TABLES (migrated from ksf_CRM)
-- ============================================================================

-- CRM Customers table (extends FA debtors)
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_customers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `debtor_no` VARCHAR(20) NOT NULL,
    `customer_type_id` INT(11) DEFAULT NULL,
    `customer_segment_id` INT(11) DEFAULT NULL,
    `territory_id` INT(11) DEFAULT NULL,
    `customer_since` DATE DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `industry` VARCHAR(100) DEFAULT NULL,
    `employee_count` INT(11) DEFAULT NULL,
    `annual_revenue` DECIMAL(15,2) DEFAULT NULL,
    `parent_company` VARCHAR(100) DEFAULT NULL,
    `latitude` DECIMAL(10,8) DEFAULT NULL,
    `longitude` DECIMAL(11,8) DEFAULT NULL,
    `edi_enabled` TINYINT(1) DEFAULT 0,
    `marketing_opt_out` TINYINT(1) DEFAULT 0,
    `preferred_contact_method` VARCHAR(20) DEFAULT 'email',
    `last_contact_date` DATETIME DEFAULT NULL,
    `next_followup_date` DATETIME DEFAULT NULL,
    `account_manager` VARCHAR(100) DEFAULT NULL,
    `credit_rating` VARCHAR(20) DEFAULT 'good',
    `payment_reliability` DECIMAL(5,2) DEFAULT 100.00,
    `inactive` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_customer_type` (`customer_type_id`),
    KEY `idx_territory` (`territory_id`),
    KEY `idx_inactive` (`inactive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Contacts table
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_contacts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `debtor_no` VARCHAR(20) NOT NULL,
    `contact_role_id` INT(11) DEFAULT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `title` VARCHAR(50) DEFAULT NULL,
    `department` VARCHAR(50) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `mobile` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT,
    `notes` TEXT,
    `is_primary` TINYINT(1) DEFAULT 0,
    `inactive` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTACT RELATIONSHIPS (person-to-person)
-- ============================================================================
-- Links two crm_persons with a relationship type.
-- Directed relationships (parent->child) use is_directed=1.
-- Undirected relationships (spouse) use is_directed=0.
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_contact_relationships` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_a_id` INT(11) NOT NULL COMMENT 'FK to crm_persons.id',
    `person_b_id` INT(11) NOT NULL COMMENT 'FK to crm_persons.id',
    `relation_type` VARCHAR(30) NOT NULL COMMENT 'parent, child, spouse, sibling, etc.',
    `is_directed` TINYINT(1) DEFAULT 0 COMMENT '0=undirected (spouse), 1=directed (parent->child)',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_pair_type` (`person_a_id`, `person_b_id`, `relation_type`),
    KEY `idx_person_a` (`person_a_id`),
    KEY `idx_person_b` (`person_b_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ACCOUNT RELATIONSHIPS (account-to-account, nested entities)
-- ============================================================================
-- Links two debtor accounts (debtors_master) for ownership/subsidiary hierarchies.
-- Examples: Trust -> HoldCo -> OpCo, Parent Company -> Subsidiary
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_account_relationships` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `parent_debtor_no` VARCHAR(20) NOT NULL COMMENT 'FK to debtors_master.debtor_no',
    `child_debtor_no` VARCHAR(20) NOT NULL COMMENT 'FK to debtors_master.debtor_no',
    `relation_type` VARCHAR(30) NOT NULL COMMENT 'owns, subsidiary, trustee_of, beneficiary_of',
    `ownership_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'ownership percentage',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_parent_child_type` (`parent_debtor_no`, `child_debtor_no`, `relation_type`),
    KEY `idx_parent` (`parent_debtor_no`),
    KEY `idx_child` (`child_debtor_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PERSON-ACCOUNT ROLES (person-to-account)
-- ============================================================================
-- Links a person to an account with a specific role.
-- Examples: director, beneficiary, trustee, employee, owner, signatory
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_person_account_roles` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to crm_persons.id',
    `debtor_no` VARCHAR(20) NOT NULL COMMENT 'FK to debtors_master.debtor_no',
    `role` VARCHAR(30) NOT NULL COMMENT 'director, beneficiary, trustee, employee, owner',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_person_account_role` (`person_id`, `debtor_no`, `role`),
    KEY `idx_person` (`person_id`),
    KEY `idx_account` (`debtor_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LIFE EVENTS (GEDCOM-style events for persons)
-- ============================================================================
-- Stores birth, death, marriage, divorce, and custom business events.
-- details_json holds free-form structured data for any event type.
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_life_events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `person_id` INT(11) NOT NULL COMMENT 'FK to crm_persons.id',
    `event_type` VARCHAR(20) NOT NULL COMMENT 'BIRT, DEAT, MARR, DIV, EDUC, RETI, CUST',
    `event_date` DATE DEFAULT NULL,
    `event_place` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `gedcom_tag` VARCHAR(30) DEFAULT NULL COMMENT 'original GEDCOM tag',
    `details_json` TEXT COMMENT 'free-form structured data per event type',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_person` (`person_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_event_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- EXISTING CRM TABLES (unchanged from original ksf_CRM)
-- ============================================================================

-- CRM Opportunities table
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_opportunities` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `opportunity_name` VARCHAR(100) NOT NULL,
    `debtor_no` VARCHAR(20) DEFAULT NULL,
    `contact_id` INT(11) DEFAULT NULL,
    `sales_person` VARCHAR(100) DEFAULT NULL,
    `opportunity_type` VARCHAR(50) DEFAULT NULL,
    `realm` VARCHAR(50) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'prospecting',
    `stage` VARCHAR(30) DEFAULT 'qualification',
    `source` VARCHAR(50) DEFAULT NULL,
    `estimated_value` DECIMAL(15,2) DEFAULT NULL,
    `probability` DECIMAL(5,2) DEFAULT 0,
    `expected_close_date` DATE DEFAULT NULL,
    `actual_close_date` DATE DEFAULT NULL,
    `lost_reason` TEXT,
    `won_notes` TEXT,
    `notes` TEXT,
    `assigned_to` VARCHAR(100) DEFAULT NULL,
    `lead_id` INT(11) DEFAULT NULL,
    `campaign_id` INT(11) DEFAULT NULL,
    `quote_id` INT(11) DEFAULT NULL,
    `project_id` INT(11) DEFAULT NULL,
    `inactive` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_lead_id` (`lead_id`),
    KEY `idx_realm` (`realm`),
    KEY `idx_status` (`status`),
    KEY `idx_stage` (`stage`),
    KEY `idx_expected_close` (`expected_close_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Communications table
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_communications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `debtor_no` VARCHAR(20) DEFAULT NULL,
    `contact_id` INT(11) DEFAULT NULL,
    `opportunity_id` INT(11) DEFAULT NULL,
    `communication_type` VARCHAR(20) NOT NULL,
    `direction` VARCHAR(10) DEFAULT 'outbound',
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT,
    `email_from` VARCHAR(100) DEFAULT NULL,
    `email_to` VARCHAR(100) DEFAULT NULL,
    `phone_number` VARCHAR(20) DEFAULT NULL,
    `duration_minutes` INT(11) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'completed',
    `scheduled_date` DATETIME DEFAULT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `assigned_to` VARCHAR(100) DEFAULT NULL,
    `priority` VARCHAR(10) DEFAULT 'medium',
    `follow_up_required` TINYINT(1) DEFAULT 0,
    `follow_up_date` DATETIME DEFAULT NULL,
    `notes` TEXT,
    `email_message_id` VARCHAR(255) DEFAULT NULL,
    `attachment_path` VARCHAR(500) DEFAULT NULL,
    `created_by` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_follow_up` (`follow_up_required`, `follow_up_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Customer Types
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_customer_types` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `inactive` TINYINT(1) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Territories
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_territories` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `region` VARCHAR(50) DEFAULT NULL,
    `inactive` TINYINT(1) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Activity Log
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_activity_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `activity_type` VARCHAR(30) NOT NULL,
    `entity_type` VARCHAR(30) NOT NULL,
    `entity_id` INT(11) NOT NULL,
    `debtor_no` VARCHAR(20) DEFAULT NULL,
    `user_id` VARCHAR(100) DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT,
    `old_values` TEXT,
    `new_values` TEXT,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_debtor_no` (`debtor_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Leads
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_leads` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `debtor_no` VARCHAR(20) NOT NULL,
    `lead_source` VARCHAR(50) DEFAULT NULL,
    `lead_status` VARCHAR(30) DEFAULT 'new',
    `rating` VARCHAR(30) DEFAULT NULL,
    `annual_revenue` DECIMAL(15,2) DEFAULT NULL,
    `employee_count` INT(11) DEFAULT NULL,
    `industry` VARCHAR(50) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `address` TEXT,
    `assigned_to` VARCHAR(100) DEFAULT NULL,
    `campaign_id` INT(11) DEFAULT NULL,
    `converted_date` DATETIME DEFAULT NULL,
    `converted_to_debtor_no` VARCHAR(20) DEFAULT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_lead_status` (`lead_status`),
    KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Contact Accounts (cross-account contact assignments)
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_contact_accounts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `contact_id` INT(11) NOT NULL,
    `debtor_no` VARCHAR(20) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `role` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_contact_debtor` (`contact_id`, `debtor_no`),
    KEY `idx_debtor_no` (`debtor_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Realms (opportunity realms/categories)
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_realms` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `requires_quote` TINYINT(1) DEFAULT 0,
    `requires_project` TINYINT(1) DEFAULT 0,
    `default_stage` VARCHAR(30) DEFAULT 'qualification',
    `stages_json` TEXT,
    `inactive` TINYINT(1) DEFAULT 0,
    `sort_order` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Quotes
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_quotes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quote_no` VARCHAR(30) NOT NULL,
    `opportunity_id` INT(11) DEFAULT NULL,
    `debtor_no` VARCHAR(20) DEFAULT NULL,
    `contact_id` INT(11) DEFAULT NULL,
    `quote_date` DATE DEFAULT NULL,
    `valid_until` DATE DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'draft',
    `subtotal` DECIMAL(15,2) DEFAULT 0,
    `tax_rate` DECIMAL(5,2) DEFAULT 0,
    `tax_amount` DECIMAL(15,2) DEFAULT 0,
    `total` DECIMAL(15,2) DEFAULT 0,
    `notes` TEXT,
    `terms` TEXT,
    `created_by` VARCHAR(100) DEFAULT NULL,
    `approved_by` VARCHAR(100) DEFAULT NULL,
    `approved_date` DATETIME DEFAULT NULL,
    `sent_date` DATETIME DEFAULT NULL,
    `accepted_date` DATETIME DEFAULT NULL,
    `rejected_date` DATETIME DEFAULT NULL,
    `inactive` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_quote_no` (`quote_no`),
    KEY `idx_opportunity_id` (`opportunity_id`),
    KEY `idx_debtor_no` (`debtor_no`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CRM Quote Items
CREATE TABLE IF NOT EXISTS `@TB_PREF@fa_crm_quote_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quote_id` INT(11) NOT NULL,
    `line_number` INT(11) DEFAULT 0,
    `item_description` VARCHAR(255) NOT NULL,
    `quantity` DECIMAL(10,2) DEFAULT 1,
    `unit_price` DECIMAL(15,2) DEFAULT 0,
    `unit` VARCHAR(20) DEFAULT NULL,
    `discount_percent` DECIMAL(5,2) DEFAULT 0,
    `discount_amount` DECIMAL(15,2) DEFAULT 0,
    `line_total` DECIMAL(15,2) DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_quote_id` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Insert Initial Data
-- ============================================================================

INSERT IGNORE INTO `@TB_PREF@fa_crm_customer_types` (`name`, `description`, `sort_order`) VALUES
('Prospect', 'Potential new customer', 1),
('Active', 'Current active customer', 2),
('Inactive', 'Former customer', 3),
('VIP', 'High-value customer', 4),
('Partner', 'Business partner', 5);

INSERT IGNORE INTO `@TB_PREF@fa_crm_territories` (`name`, `description`, `region`, `sort_order`) VALUES
('North', 'Northern region', 'North', 1),
('South', 'Southern region', 'South', 2),
('East', 'Eastern region', 'East', 3),
('West', 'Western region', 'West', 4),
('Central', 'Central region', 'Central', 5);
