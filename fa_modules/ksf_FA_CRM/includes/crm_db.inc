<?php
/**
 * CRM Database Functions
 *
 * WebERP-style CRM database operations for FrontAccounting
 * 
 * Events: Uses ksfraser/event for PSR-14 events, which also trigger 
 * FA hooks for cross-module integration
 */

// Try to load ksfraser/event for PSR-14 events
@include_once __DIR__ . '/../../vendor/ksfraser/event/src/Ksfraser/Event/EventManager.php';
$use_events = class_exists('Ksfraser\Event\EventManager');

function crm_dispatch_event(string $eventName, $eventData = null): void
{
    global $use_events;
    
    // Dispatch PSR-14 event (for non-FA modules)
    if ($use_events && class_exists('Ksfraser\Event\EventManager')) {
        try {
            \Ksfraser\Event\EventManager::dispatchEvent($eventName, $eventData);
        } catch (Exception $e) {
            error_log('CRM Event Error: ' . $e->getMessage());
        }
    }
    
    // Dispatch FA hooks (for FA modules)
    if (function_exists('add_hook')) {
        add_hook($eventName, function($data) use ($eventData) {
            return $eventData;
        });
    }
}

// ============================================================================
// CRM CUSTOMER FUNCTIONS
// ============================================================================

function update_customer_crm_data($customer_id, $crm_data)
{
	// Check if CRM record exists
	$sql = "SELECT id FROM " . TB_PREF . "fa_crm_customers WHERE debtor_no = " . db_escape($customer_id);
	$result = db_query($sql);
	$exists = db_num_rows($result) > 0;

	if ($exists) {
		$sql = "UPDATE " . TB_PREF . "fa_crm_customers SET
			customer_type_id = " . db_escape($crm_data['customer_type_id']) . ",
			customer_segment_id = " . db_escape($crm_data['customer_segment_id']) . ",
			territory_id = " . db_escape($crm_data['territory_id']) . ",
			customer_since = " . db_escape($crm_data['customer_since']) . ",
			website = " . db_escape($crm_data['website']) . ",
			industry = " . db_escape($crm_data['industry']) . ",
			employee_count = " . db_escape($crm_data['employee_count']) . ",
			annual_revenue = " . db_escape($crm_data['annual_revenue']) . ",
			parent_company = " . db_escape($crm_data['parent_company']) . ",
			latitude = " . db_escape($crm_data['latitude']) . ",
			longitude = " . db_escape($crm_data['longitude']) . ",
			edi_enabled = " . (int)$crm_data['edi_enabled'] . ",
			marketing_opt_out = " . (int)$crm_data['marketing_opt_out'] . ",
			preferred_contact_method = " . db_escape($crm_data['preferred_contact_method']) . ",
			last_contact_date = " . db_escape($crm_data['last_contact_date']) . ",
			next_followup_date = " . db_escape($crm_data['next_followup_date']) . ",
			account_manager = " . db_escape($crm_data['account_manager']) . ",
			credit_rating = " . db_escape($crm_data['credit_rating']) . ",
			payment_reliability = " . (float)$crm_data['payment_reliability'] . "
		WHERE debtor_no = " . db_escape($customer_id);
	} else {
		$sql = "INSERT INTO " . TB_PREF . "fa_crm_customers
			(debtor_no, customer_type_id, customer_segment_id, territory_id, customer_since,
			website, industry, employee_count, annual_revenue, parent_company, latitude, longitude,
			edi_enabled, marketing_opt_out, preferred_contact_method, last_contact_date,
			next_followup_date, account_manager, credit_rating, payment_reliability)
		VALUES (" . db_escape($customer_id) . ", " . db_escape($crm_data['customer_type_id']) . ",
			" . db_escape($crm_data['customer_segment_id']) . ", " . db_escape($crm_data['territory_id']) . ",
			" . db_escape($crm_data['customer_since']) . ", " . db_escape($crm_data['website']) . ",
			" . db_escape($crm_data['industry']) . ", " . db_escape($crm_data['employee_count']) . ",
			" . db_escape($crm_data['annual_revenue']) . ", " . db_escape($crm_data['parent_company']) . ",
			" . db_escape($crm_data['latitude']) . ", " . db_escape($crm_data['longitude']) . ",
			" . (int)$crm_data['edi_enabled'] . ", " . (int)$crm_data['marketing_opt_out'] . ",
			" . db_escape($crm_data['preferred_contact_method']) . ", " . db_escape($crm_data['last_contact_date']) . ",
			" . db_escape($crm_data['next_followup_date']) . ", " . db_escape($crm_data['account_manager']) . ",
			" . db_escape($crm_data['credit_rating']) . ", " . (float)$crm_data['payment_reliability'] . ")";
	}

	db_query($sql, "Could not update customer CRM data");
}

function get_customer_crm_data($customer_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_customers WHERE debtor_no = " . db_escape($customer_id);
	$result = db_query($sql, "Could not get customer CRM data");
	return db_fetch($result);
}

function delete_customer_crm_data($customer_id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_customers WHERE debtor_no = " . db_escape($customer_id);
	db_query($sql, "Could not delete customer CRM data");
}

// ============================================================================
// CUSTOMER TYPES FUNCTIONS
// ============================================================================

function add_customer_type($type_name, $description, $discount_percent = 0, $credit_limit = 0, $payment_terms = null)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_customer_types
		(type_name, description, discount_percent, credit_limit, payment_terms)
		VALUES (" . db_escape($type_name) . ", " . db_escape($description) . ",
		" . (float)$discount_percent . ", " . (float)$credit_limit . ", " . db_escape($payment_terms) . ")";

	db_query($sql, "Could not add customer type");
	return db_insert_id();
}

function update_customer_type($id, $type_name, $description, $discount_percent = 0, $credit_limit = 0, $payment_terms = null)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_customer_types SET
		type_name = " . db_escape($type_name) . ",
		description = " . db_escape($description) . ",
		discount_percent = " . (float)$discount_percent . ",
		credit_limit = " . (float)$credit_limit . ",
		payment_terms = " . db_escape($payment_terms) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update customer type");
}

function delete_customer_type($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_customer_types WHERE id = " . (int)$id;
	db_query($sql, "Could not delete customer type");
}

function get_customer_types($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_customer_types";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY type_name";

	$result = db_query($sql, "Could not get customer types");
	return $result;
}

function get_customer_type($id)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_customer_types WHERE id = " . (int)$id;
	$result = db_query($sql, "Could not get customer type");
	return db_fetch($result);
}

// ============================================================================
// CONTACT MANAGEMENT FUNCTIONS
// ============================================================================

function add_customer_contact($debtor_no, $contact_data)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_contacts
		(debtor_no, contact_role_id, first_name, last_name, title, department,
		phone, mobile, email, address, notes, is_primary)
		VALUES (" . db_escape($debtor_no) . ", " . db_escape($contact_data['contact_role_id']) . ",
			" . db_escape($contact_data['first_name']) . ", " . db_escape($contact_data['last_name']) . ",
			" . db_escape($contact_data['title']) . ", " . db_escape($contact_data['department']) . ",
			" . db_escape($contact_data['phone']) . ", " . db_escape($contact_data['mobile']) . ",
			" . db_escape($contact_data['email']) . ", " . db_escape($contact_data['address']) . ",
			" . db_escape($contact_data['notes']) . ", " . (int)$contact_data['is_primary'] . ")";

	db_query($sql, "Could not add customer contact");
	$contact_id = db_insert_id();
	
	// Dispatch events
	crm_dispatch_event('crm.contact.created', [
		'contact_id' => $contact_id,
		'debtor_no' => $debtor_no,
		'data' => $contact_data,
	]);
	crm_dispatch_event('db_postinsert', ['table' => 'fa_crm_contacts', 'id' => $contact_id]);
	
	return $contact_id;
}

function update_customer_contact($contact_id, $contact_data)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_contacts SET
		contact_role_id = " . db_escape($contact_data['contact_role_id']) . ",
		first_name = " . db_escape($contact_data['first_name']) . ",
		last_name = " . db_escape($contact_data['last_name']) . ",
		title = " . db_escape($contact_data['title']) . ",
		department = " . db_escape($contact_data['department']) . ",
		phone = " . db_escape($contact_data['phone']) . ",
		mobile = " . db_escape($contact_data['mobile']) . ",
		email = " . db_escape($contact_data['email']) . ",
		address = " . db_escape($contact_data['address']) . ",
		notes = " . db_escape($contact_data['notes']) . ",
		is_primary = " . (int)$contact_data['is_primary'] . "
	WHERE id = " . (int)$contact_id;

	db_query($sql, "Could not update customer contact");
	
	// Dispatch events
	crm_dispatch_event('crm.contact.updated', [
		'contact_id' => $contact_id,
		'data' => $contact_data,
	]);
	crm_dispatch_event('db_postupdate', ['table' => 'fa_crm_contacts', 'id' => $contact_id]);
}

function delete_customer_contact($contact_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_contacts WHERE id = " . (int)$contact_id;
	$result = db_query($sql);
	$old_data = db_fetch_assoc($result);
	
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_contacts WHERE id = " . (int)$contact_id;
	db_query($sql, "Could not delete customer contact");
	
	// Dispatch events
	crm_dispatch_event('crm.contact.deleted', [
		'contact_id' => $contact_id,
		'old_data' => $old_data,
	]);
	crm_dispatch_event('db_postdelete', ['table' => 'fa_crm_contacts', 'id' => $contact_id]);
}

function get_customer_contacts($debtor_no, $active_only = true)
{
	$sql = "SELECT c.*, r.role_name FROM " . TB_PREF . "fa_crm_contacts c
		LEFT JOIN " . TB_PREF . "crm_contact_roles r ON c.contact_role_id = r.id
		WHERE c.debtor_no = " . db_escape($debtor_no);
	if ($active_only) {
		$sql .= " AND c.inactive = 0";
	}
	$sql .= " ORDER BY c.is_primary DESC, c.last_name, c.first_name";

	$result = db_query($sql, "Could not get customer contacts");
	return $result;
}

function get_customer_contact($contact_id)
{
	$sql = "SELECT c.*, r.role_name FROM " . TB_PREF . "fa_crm_contacts c
		LEFT JOIN " . TB_PREF . "crm_contact_roles r ON c.contact_role_id = r.id
		WHERE c.id = " . (int)$contact_id;

	$result = db_query($sql, "Could not get customer contact");
	return db_fetch($result);
}

function get_primary_contact($debtor_no)
{
	$sql = "SELECT c.*, r.role_name FROM " . TB_PREF . "fa_crm_contacts c
		LEFT JOIN " . TB_PREF . "crm_contact_roles r ON c.contact_role_id = r.id
		WHERE c.debtor_no = " . db_escape($debtor_no) . " AND c.is_primary = 1 AND c.inactive = 0
		LIMIT 1";

	$result = db_query($sql, "Could not get primary contact");
	return db_fetch($result);
}

// ============================================================================
// COMMUNICATIONS TRACKING FUNCTIONS
// ============================================================================

function add_communication($communication_data)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_communications
		(debtor_no, contact_id, communication_type, direction, subject, message,
		email_from, email_to, phone_number, duration_minutes, status, scheduled_date,
		completed_date, assigned_to, priority, follow_up_required, follow_up_date,
		notes, email_message_id, attachment_path, created_by)
		VALUES (" . db_escape($communication_data['debtor_no']) . ",
			" . db_escape($communication_data['contact_id']) . ",
			" . db_escape($communication_data['communication_type']) . ",
			" . db_escape($communication_data['direction']) . ",
			" . db_escape($communication_data['subject']) . ",
			" . db_escape($communication_data['message']) . ",
			" . db_escape($communication_data['email_from']) . ",
			" . db_escape($communication_data['email_to']) . ",
			" . db_escape($communication_data['phone_number']) . ",
			" . db_escape($communication_data['duration_minutes']) . ",
			" . db_escape($communication_data['status']) . ",
			" . db_escape($communication_data['scheduled_date']) . ",
			" . db_escape($communication_data['completed_date']) . ",
			" . db_escape($communication_data['assigned_to']) . ",
			" . db_escape($communication_data['priority']) . ",
			" . (int)$communication_data['follow_up_required'] . ",
			" . db_escape($communication_data['follow_up_date']) . ",
			" . db_escape($communication_data['notes']) . ",
			" . db_escape($communication_data['email_message_id']) . ",
			" . db_escape($communication_data['attachment_path']) . ",
			" . db_escape($communication_data['created_by']) . ")";

	db_query($sql, "Could not add communication");
	return db_insert_id();
}

function update_communication($communication_id, $communication_data)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_communications SET
		contact_id = " . db_escape($communication_data['contact_id']) . ",
		communication_type = " . db_escape($communication_data['communication_type']) . ",
		direction = " . db_escape($communication_data['direction']) . ",
		subject = " . db_escape($communication_data['subject']) . ",
		message = " . db_escape($communication_data['message']) . ",
		email_from = " . db_escape($communication_data['email_from']) . ",
		email_to = " . db_escape($communication_data['email_to']) . ",
		phone_number = " . db_escape($communication_data['phone_number']) . ",
		duration_minutes = " . db_escape($communication_data['duration_minutes']) . ",
		status = " . db_escape($communication_data['status']) . ",
		scheduled_date = " . db_escape($communication_data['scheduled_date']) . ",
		completed_date = " . db_escape($communication_data['completed_date']) . ",
		assigned_to = " . db_escape($communication_data['assigned_to']) . ",
		priority = " . db_escape($communication_data['priority']) . ",
		follow_up_required = " . (int)$communication_data['follow_up_required'] . ",
		follow_up_date = " . db_escape($communication_data['follow_up_date']) . ",
		notes = " . db_escape($communication_data['notes']) . ",
		email_message_id = " . db_escape($communication_data['email_message_id']) . ",
		attachment_path = " . db_escape($communication_data['attachment_path']) . "
	WHERE id = " . (int)$communication_id;

	db_query($sql, "Could not update communication");
}

function delete_communication($communication_id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_communications WHERE id = " . (int)$communication_id;
	db_query($sql, "Could not delete communication");
}

function get_customer_communications($debtor_no, $limit = 50, $offset = 0)
{
	$sql = "SELECT c.*, ct.first_name, ct.last_name, ct.email as contact_email
		FROM " . TB_PREF . "fa_crm_communications c
		LEFT JOIN " . TB_PREF . "fa_crm_contacts ct ON c.contact_id = ct.id
		WHERE c.debtor_no = " . db_escape($debtor_no) . "
		ORDER BY c.created_at DESC
		LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

	$result = db_query($sql, "Could not get customer communications");
	return $result;
}

function get_communication($communication_id)
{
	$sql = "SELECT c.*, ct.first_name, ct.last_name, ct.email as contact_email
		FROM " . TB_PREF . "fa_crm_communications c
		LEFT JOIN " . TB_PREF . "fa_crm_contacts ct ON c.contact_id = ct.id
		WHERE c.id = " . (int)$communication_id;

	$result = db_query($sql, "Could not get communication");
	return db_fetch($result);
}

function get_pending_followups($assigned_to = null)
{
	$sql = "SELECT c.*, d.name as customer_name, ct.first_name, ct.last_name
		FROM " . TB_PREF . "fa_crm_communications c
		JOIN " . TB_PREF . "debtors_master d ON c.debtor_no = d.debtor_no
		LEFT JOIN " . TB_PREF . "fa_crm_contacts ct ON c.contact_id = ct.id
		WHERE c.follow_up_required = 1
		AND c.follow_up_date <= CURDATE()
		AND c.status != 'cancelled'";

	if ($assigned_to) {
		$sql .= " AND c.assigned_to = " . db_escape($assigned_to);
	}

	$sql .= " ORDER BY c.follow_up_date ASC, c.priority DESC";

	$result = db_query($sql, "Could not get pending followups");
	return $result;
}

function get_communications_by_email($email_address, $limit = 100)
{
	$sql = "SELECT c.*, d.name as customer_name
		FROM " . TB_PREF . "fa_crm_communications c
		JOIN " . TB_PREF . "debtors_master d ON c.debtor_no = d.debtor_no
		WHERE (c.email_from = " . db_escape($email_address) . "
		OR c.email_to = " . db_escape($email_address) . ")
		AND c.communication_type = 'email'
		ORDER BY c.created_at DESC
		LIMIT " . (int)$limit;

	$result = db_query($sql, "Could not get communications by email");
	return $result;
}

// ============================================================================
// EMAIL IMPORT FUNCTIONS
// ============================================================================

function add_email_account($account_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_email_accounts
		(account_name, email_address, smtp_host, smtp_port, smtp_username, smtp_password,
		smtp_encryption, imap_host, imap_port, imap_username, imap_password, imap_encryption,
		auto_import, import_frequency_minutes)
		VALUES (" . db_escape($account_data['account_name']) . ",
			" . db_escape($account_data['email_address']) . ",
			" . db_escape($account_data['smtp_host']) . ",
			" . (int)$account_data['smtp_port'] . ",
			" . db_escape($account_data['smtp_username']) . ",
			" . db_escape($account_data['smtp_password']) . ",
			" . db_escape($account_data['smtp_encryption']) . ",
			" . db_escape($account_data['imap_host']) . ",
			" . (int)$account_data['imap_port'] . ",
			" . db_escape($account_data['imap_username']) . ",
			" . db_escape($account_data['imap_password']) . ",
			" . db_escape($account_data['imap_encryption']) . ",
			" . (int)$account_data['auto_import'] . ",
			" . (int)$account_data['import_frequency_minutes'] . ")";

	db_query($sql, "Could not add email account");
	return db_insert_id();
}

function update_email_account($account_id, $account_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_email_accounts SET
		account_name = " . db_escape($account_data['account_name']) . ",
		email_address = " . db_escape($account_data['email_address']) . ",
		smtp_host = " . db_escape($account_data['smtp_host']) . ",
		smtp_port = " . (int)$account_data['smtp_port'] . ",
		smtp_username = " . db_escape($account_data['smtp_username']) . ",
		smtp_password = " . db_escape($account_data['smtp_password']) . ",
		smtp_encryption = " . db_escape($account_data['smtp_encryption']) . ",
		imap_host = " . db_escape($account_data['imap_host']) . ",
		imap_port = " . (int)$account_data['imap_port'] . ",
		imap_username = " . db_escape($account_data['imap_username']) . ",
		imap_password = " . db_escape($account_data['imap_password']) . ",
		imap_encryption = " . db_escape($account_data['imap_encryption']) . ",
		auto_import = " . (int)$account_data['auto_import'] . ",
		import_frequency_minutes = " . (int)$account_data['import_frequency_minutes'] . ",
		active = " . (int)$account_data['active'] . "
	WHERE id = " . (int)$account_id;

	db_query($sql, "Could not update email account");
}

function delete_email_account($account_id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_email_accounts WHERE id = " . (int)$account_id;
	db_query($sql, "Could not delete email account");
}

function get_email_accounts($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_email_accounts";
	if ($active_only) {
		$sql .= " WHERE active = 1";
	}
	$sql .= " ORDER BY account_name";

	$result = db_query($sql, "Could not get email accounts");
	return $result;
}

function get_email_account($account_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_email_accounts WHERE id = " . (int)$account_id;
	$result = db_query($sql, "Could not get email account");
	return db_fetch($result);
}

function update_email_sync_time($account_id)
{
	$sql = "UPDATE " . TB_PREF . "crm_email_accounts SET last_sync = NOW() WHERE id = " . (int)$account_id;
	db_query($sql, "Could not update email sync time");
}

function get_accounts_due_for_sync()
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_email_accounts
		WHERE active = 1 AND auto_import = 1
		AND (last_sync IS NULL OR last_sync <= DATE_SUB(NOW(), INTERVAL import_frequency_minutes MINUTE))";

	$result = db_query($sql, "Could not get accounts due for sync");
	return $result;
}

// ============================================================================
// TERRITORY FUNCTIONS
// ============================================================================

function add_territory($territory_name, $description, $sales_person = null, $region = null)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_territories
		(territory_name, description, sales_person, region)
		VALUES (" . db_escape($territory_name) . ", " . db_escape($description) . ",
		" . db_escape($sales_person) . ", " . db_escape($region) . ")";

	db_query($sql, "Could not add territory");
	return db_insert_id();
}

function update_territory($id, $territory_name, $description, $sales_person = null, $region = null)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_territories SET
		territory_name = " . db_escape($territory_name) . ",
		description = " . db_escape($description) . ",
		sales_person = " . db_escape($sales_person) . ",
		region = " . db_escape($region) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update territory");
}

function delete_territory($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_territories WHERE id = " . (int)$id;
	db_query($sql, "Could not delete territory");
}

function get_territories($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_territories";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY territory_name";

	$result = db_query($sql, "Could not get territories");
	return $result;
}

function get_territory($id)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_territories WHERE id = " . (int)$id;
	$result = db_query($sql, "Could not get territory");
	return db_fetch($result);
}

// ============================================================================
// OPPORTUNITIES FUNCTIONS
// ============================================================================

function add_opportunity($opportunity_data)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_opportunities
		(opportunity_name, debtor_no, contact_id, sales_person, opportunity_type, status,
		estimated_value, probability, expected_close_date, notes)
		VALUES (" . db_escape($opportunity_data['opportunity_name']) . ",
			" . db_escape($opportunity_data['debtor_no']) . ",
			" . db_escape($opportunity_data['contact_id']) . ",
			" . db_escape($opportunity_data['sales_person']) . ",
			" . db_escape($opportunity_data['opportunity_type']) . ",
			" . db_escape($opportunity_data['status']) . ",
			" . (float)$opportunity_data['estimated_value'] . ",
			" . (float)$opportunity_data['probability'] . ",
			" . db_escape($opportunity_data['expected_close_date']) . ",
			" . db_escape($opportunity_data['notes']) . ")";

	db_query($sql, "Could not add opportunity");
	return db_insert_id();
}

function update_opportunity($opportunity_id, $opportunity_data)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
		opportunity_name = " . db_escape($opportunity_data['opportunity_name']) . ",
		contact_id = " . db_escape($opportunity_data['contact_id']) . ",
		sales_person = " . db_escape($opportunity_data['sales_person']) . ",
		opportunity_type = " . db_escape($opportunity_data['opportunity_type']) . ",
		status = " . db_escape($opportunity_data['status']) . ",
		estimated_value = " . (float)$opportunity_data['estimated_value'] . ",
		probability = " . (float)$opportunity_data['probability'] . ",
		expected_close_date = " . db_escape($opportunity_data['expected_close_date']) . ",
		notes = " . db_escape($opportunity_data['notes']) . "
	WHERE id = " . (int)$opportunity_id;

	db_query($sql, "Could not update opportunity");
}

function delete_opportunity($opportunity_id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_opportunities WHERE id = " . (int)$opportunity_id;
	db_query($sql, "Could not delete opportunity");
}

function get_customer_opportunities($debtor_no, $active_only = true)
{
	$sql = "SELECT o.*, c.first_name, c.last_name FROM " . TB_PREF . "fa_crm_opportunities o
		LEFT JOIN " . TB_PREF . "fa_crm_contacts c ON o.contact_id = c.id
		WHERE o.debtor_no = " . db_escape($debtor_no);
	if ($active_only) {
		$sql .= " AND o.status NOT IN ('closed_won', 'closed_lost', 'cancelled')";
	}
	$sql .= " ORDER BY o.created_at DESC";

	$result = db_query($sql, "Could not get customer opportunities");
	return $result;
}

function get_opportunity($opportunity_id)
{
	$sql = "SELECT o.*, c.first_name, c.last_name, d.name as customer_name
		FROM " . TB_PREF . "fa_crm_opportunities o
		LEFT JOIN " . TB_PREF . "fa_crm_contacts c ON o.contact_id = c.id
		JOIN " . TB_PREF . "debtors_master d ON o.debtor_no = d.debtor_no
		WHERE o.id = " . (int)$opportunity_id;

	$result = db_query($sql, "Could not get opportunity");
	return db_fetch($result);
}

// ============================================================================
// ANALYTICS FUNCTIONS
// ============================================================================

function update_customer_analytics($customer_id)
{
	// Calculate customer analytics data
	$analytics = calculate_customer_analytics($customer_id);

	// Insert or update analytics record
	$sql = "INSERT INTO " . TB_PREF . "crm_customer_analytics
		(debtor_no, period_start, period_end, total_sales, total_payments,
		outstanding_balance, payment_days_avg, customer_lifetime_value,
		last_communication_date, communication_count)
		VALUES (" . db_escape($customer_id) . ", " . db_escape($analytics['period_start']) . ",
			" . db_escape($analytics['period_end']) . ", " . $analytics['total_sales'] . ",
			" . $analytics['total_payments'] . ", " . $analytics['outstanding_balance'] . ",
			" . $analytics['payment_days_avg'] . ", " . $analytics['customer_lifetime_value'] . ",
			" . db_escape($analytics['last_communication_date']) . ", " . $analytics['communication_count'] . ")
		ON DUPLICATE KEY UPDATE
		total_sales = VALUES(total_sales),
		total_payments = VALUES(total_payments),
		outstanding_balance = VALUES(outstanding_balance),
		payment_days_avg = VALUES(payment_days_avg),
		customer_lifetime_value = VALUES(customer_lifetime_value),
		last_communication_date = VALUES(last_communication_date),
		communication_count = VALUES(communication_count)";

	db_query($sql);
}

function calculate_customer_analytics($customer_id)
{
	// Calculate total sales
	$sql = "SELECT SUM(ov_amount + ov_gst + ov_freight + ov_freight_tax - ov_discount) as total_sales
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type IN (" . ST_SALESINVOICE . ", " . ST_CUSTCREDIT . ")";

	$result = db_query($sql);
	$sales_row = db_fetch($result);
	$total_sales = $sales_row['total_sales'] ? $sales_row['total_sales'] : 0;

	// Calculate total payments
	$sql = "SELECT SUM(ov_amount) as total_payments
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type = " . ST_CUSTPAYMENT;

	$result = db_query($sql);
	$payment_row = db_fetch($result);
	$total_payments = $payment_row['total_payments'] ? $payment_row['total_payments'] : 0;

	// Calculate outstanding balance
	$sql = "SELECT SUM(ov_amount + ov_gst + ov_freight + ov_freight_tax - ov_discount - alloc) as outstanding
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type IN (" . ST_SALESINVOICE . ", " . ST_CUSTCREDIT . ")";

	$result = db_query($sql);
	$outstanding_row = db_fetch($result);
	$outstanding_balance = $outstanding_row['outstanding'] ? $outstanding_row['outstanding'] : 0;

	// Calculate average payment days (simplified)
	$payment_days_avg = 30; // Default assumption

	// Customer lifetime value (simplified)
	$customer_lifetime_value = $total_sales;

	// Get communication stats
	$sql = "SELECT COUNT(*) as comm_count, MAX(created_at) as last_comm
		FROM " . TB_PREF . "fa_crm_communications
		WHERE debtor_no = " . db_escape($customer_id);

	$result = db_query($sql);
	$comm_row = db_fetch($result);
	$communication_count = $comm_row['comm_count'] ? $comm_row['comm_count'] : 0;
	$last_communication_date = $comm_row['last_comm'];

	return [
		'customer_id' => $customer_id,
		'period_start' => date('Y-m-01'),
		'period_end' => date('Y-m-t'),
		'total_sales' => $total_sales,
		'total_payments' => $total_payments,
		'outstanding_balance' => $outstanding_balance,
		'payment_days_avg' => $payment_days_avg,
		'customer_lifetime_value' => $customer_lifetime_value,
		'last_communication_date' => $last_communication_date,
		'communication_count' => $communication_count
	];
}

function get_customer_analytics($customer_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_customer_analytics
		WHERE debtor_no = " . db_escape($customer_id) . "
		ORDER BY period_end DESC LIMIT 1";

	$result = db_query($sql, "Could not get customer analytics");
	return db_fetch($result);
}

// Customer Types Functions
function add_customer_type($type_name, $description, $discount_percent = 0, $credit_limit = 0, $payment_terms = null)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_customer_types
		(type_name, description, discount_percent, credit_limit, payment_terms)
		VALUES (" . db_escape($type_name) . ", " . db_escape($description) . ",
		" . (float)$discount_percent . ", " . (float)$credit_limit . ", " . db_escape($payment_terms) . ")";

	db_query($sql, "Could not add customer type");
	return db_insert_id();
}

function update_customer_type($id, $type_name, $description, $discount_percent = 0, $credit_limit = 0, $payment_terms = null)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_customer_types SET
		type_name = " . db_escape($type_name) . ",
		description = " . db_escape($description) . ",
		discount_percent = " . (float)$discount_percent . ",
		credit_limit = " . (float)$credit_limit . ",
		payment_terms = " . db_escape($payment_terms) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update customer type");
}

function delete_customer_type($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_customer_types WHERE id = " . (int)$id;
	db_query($sql, "Could not delete customer type");
}

function get_customer_types($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_customer_types";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY type_name";

	return db_query($sql, "Could not get customer types");
}

// Customer Segments Functions
function add_customer_segment($segment_name, $description, $criteria = null)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_customer_segments
		(segment_name, description, criteria)
		VALUES (" . db_escape($segment_name) . ", " . db_escape($description) . ", " . db_escape($criteria) . ")";

	db_query($sql, "Could not add customer segment");
	return db_insert_id();
}

function update_customer_segment($id, $segment_name, $description, $criteria = null)
{
	$sql = "UPDATE " . TB_PREF . "crm_customer_segments SET
		segment_name = " . db_escape($segment_name) . ",
		description = " . db_escape($description) . ",
		criteria = " . db_escape($criteria) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update customer segment");
}

function delete_customer_segment($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_customer_segments WHERE id = " . (int)$id;
	db_query($sql, "Could not delete customer segment");
}

function get_customer_segments($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_customer_segments";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY segment_name";

	return db_query($sql, "Could not get customer segments");
}

// Contact Roles Functions
function add_contact_role($role_name, $description, $default_phone = null, $default_email = null)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_contact_roles
		(role_name, description, default_phone, default_email)
		VALUES (" . db_escape($role_name) . ", " . db_escape($description) . ",
		" . db_escape($default_phone) . ", " . db_escape($default_email) . ")";

	db_query($sql, "Could not add contact role");
	return db_insert_id();
}

function update_contact_role($id, $role_name, $description, $default_phone = null, $default_email = null)
{
	$sql = "UPDATE " . TB_PREF . "crm_contact_roles SET
		role_name = " . db_escape($role_name) . ",
		description = " . db_escape($description) . ",
		default_phone = " . db_escape($default_phone) . ",
		default_email = " . db_escape($default_email) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update contact role");
}

function delete_contact_role($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_contact_roles WHERE id = " . (int)$id;
	db_query($sql, "Could not delete contact role");
}

function get_contact_roles($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_contact_roles";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY role_name";

	return db_query($sql, "Could not get contact roles");
}

// Territories Functions
function add_territory($territory_name, $description, $sales_person = null, $region = null)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_territories
		(territory_name, description, sales_person, region)
		VALUES (" . db_escape($territory_name) . ", " . db_escape($description) . ",
		" . db_escape($sales_person) . ", " . db_escape($region) . ")";

	db_query($sql, "Could not add territory");
	return db_insert_id();
}

function update_territory($id, $territory_name, $description, $sales_person = null, $region = null)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_territories SET
		territory_name = " . db_escape($territory_name) . ",
		description = " . db_escape($description) . ",
		sales_person = " . db_escape($sales_person) . ",
		region = " . db_escape($region) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update territory");
}

function delete_territory($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_territories WHERE id = " . (int)$id;
	db_query($sql, "Could not delete territory");
}

function get_territories($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_territories";
	if ($active_only) {
		$sql .= " WHERE inactive = 0";
	}
	$sql .= " ORDER BY territory_name";

	return db_query($sql, "Could not get territories");
}

// Sales Opportunities Functions
function add_sales_opportunity($opportunity_name, $customer_id, $contact_id = null, $sales_person = null,
	$opportunity_type = 'sales', $status = 'prospect', $estimated_value = 0, $probability = 0,
	$expected_close_date = null, $notes = null)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_opportunities
		(opportunity_name, customer_id, contact_id, sales_person, opportunity_type, status,
		estimated_value, probability, expected_close_date, notes)
		VALUES (" . db_escape($opportunity_name) . ", " . db_escape($customer_id) . ",
		" . db_escape($contact_id) . ", " . db_escape($sales_person) . ", " . db_escape($opportunity_type) . ",
		" . db_escape($status) . ", " . (float)$estimated_value . ", " . (float)$probability . ",
		" . db_escape($expected_close_date) . ", " . db_escape($notes) . ")";

	db_query($sql, "Could not add sales opportunity");
	return db_insert_id();
}

function update_sales_opportunity($id, $opportunity_name, $customer_id, $contact_id = null, $sales_person = null,
	$opportunity_type = 'sales', $status = 'prospect', $estimated_value = 0, $probability = 0,
	$expected_close_date = null, $notes = null)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
		opportunity_name = " . db_escape($opportunity_name) . ",
		customer_id = " . db_escape($customer_id) . ",
		contact_id = " . db_escape($contact_id) . ",
		sales_person = " . db_escape($sales_person) . ",
		opportunity_type = " . db_escape($opportunity_type) . ",
		status = " . db_escape($status) . ",
		estimated_value = " . (float)$estimated_value . ",
		probability = " . (float)$probability . ",
		expected_close_date = " . db_escape($expected_close_date) . ",
		notes = " . db_escape($notes) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update sales opportunity");
}

function delete_sales_opportunity($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_opportunities WHERE id = " . (int)$id;
	db_query($sql, "Could not delete sales opportunity");
}

function get_customer_opportunities($customer_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "fa_crm_opportunities
	WHERE customer_id = " . db_escape($customer_id) . "
	ORDER BY expected_close_date DESC, estimated_value DESC";

	return db_query($sql, "Could not get customer opportunities");
}

// EDI Configuration Functions
function add_edi_config($customer_id, $edi_type, $edi_code, $ftp_host = null, $ftp_username = null,
	$ftp_password = null, $email_address = null, $active = 1)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_edi_config
		(customer_id, edi_type, edi_code, ftp_host, ftp_username, ftp_password, email_address, active)
		VALUES (" . db_escape($customer_id) . ", " . db_escape($edi_type) . ", " . db_escape($edi_code) . ",
		" . db_escape($ftp_host) . ", " . db_escape($ftp_username) . ", " . db_escape($ftp_password) . ",
		" . db_escape($email_address) . ", " . (int)$active . ")";

	db_query($sql, "Could not add EDI configuration");
	return db_insert_id();
}

function update_edi_config($id, $customer_id, $edi_type, $edi_code, $ftp_host = null, $ftp_username = null,
	$ftp_password = null, $email_address = null, $active = 1)
{
	$sql = "UPDATE " . TB_PREF . "crm_edi_config SET
		customer_id = " . db_escape($customer_id) . ",
		edi_type = " . db_escape($edi_type) . ",
		edi_code = " . db_escape($edi_code) . ",
		ftp_host = " . db_escape($ftp_host) . ",
		ftp_username = " . db_escape($ftp_username) . ",
		ftp_password = " . db_escape($ftp_password) . ",
		email_address = " . db_escape($email_address) . ",
		active = " . (int)$active . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update EDI configuration");
}

function get_customer_edi_config($customer_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_edi_config
	WHERE customer_id = " . db_escape($customer_id) . " AND active = 1
	LIMIT 1";

	$result = db_query($sql, "Could not get EDI configuration");
	return db_fetch($result);
}

// Customer Analytics Functions
function get_customer_analytics($customer_id, $period_start = null, $period_end = null)
{
	if (!$period_start) {
		$period_start = date('Y-m-01');
	}
	if (!$period_end) {
		$period_end = date('Y-m-t');
	}

	$sql = "SELECT * FROM " . TB_PREF . "crm_customer_analytics
	WHERE customer_id = " . db_escape($customer_id) . "
	AND period_start >= " . db_escape($period_start) . "
	AND period_end <= " . db_escape($period_end) . "
	ORDER BY period_end DESC LIMIT 1";

	$result = db_query($sql, "Could not get customer analytics");
	return db_fetch($result);
}

function calculate_customer_analytics($customer_id)
{
	// Calculate total sales
	$sql = "SELECT SUM(ov_amount + ov_gst + ov_freight + ov_freight_tax - ov_discount) as total_sales
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type IN (" . ST_SALESINVOICE . ", " . ST_CUSTCREDIT . ")";

	$result = db_query($sql);
	$sales_row = db_fetch($result);
	$total_sales = $sales_row['total_sales'] ? $sales_row['total_sales'] : 0;

	// Calculate total payments
	$sql = "SELECT SUM(ov_amount) as total_payments
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type = " . ST_CUSTPAYMENT;

	$result = db_query($sql);
	$payment_row = db_fetch($result);
	$total_payments = $payment_row['total_payments'] ? $payment_row['total_payments'] : 0;

	// Calculate outstanding balance
	$sql = "SELECT SUM(ov_amount + ov_gst + ov_freight + ov_freight_tax - ov_discount - alloc) as outstanding
	FROM " . TB_PREF . "debtor_trans
	WHERE debtor_no = " . db_escape($customer_id) . "
	AND type IN (" . ST_SALESINVOICE . ", " . ST_CUSTCREDIT . ")";

	$result = db_query($sql);
	$outstanding_row = db_fetch($result);
	$outstanding_balance = $outstanding_row['outstanding'] ? $outstanding_row['outstanding'] : 0;

	// Calculate average payment days (simplified)
	$payment_days_avg = 30; // Default assumption

	// Customer lifetime value (simplified)
	$customer_lifetime_value = $total_sales;

	return [
		'customer_id' => $customer_id,
		'period_start' => date('Y-m-01'),
		'period_end' => date('Y-m-t'),
		'total_sales' => $total_sales,
		'total_payments' => $total_payments,
		'outstanding_balance' => $outstanding_balance,
		'payment_days_avg' => $payment_days_avg,
		'customer_lifetime_value' => $customer_lifetime_value
	];
}

function update_customer_analytics($customer_id)
{
	$analytics = calculate_customer_analytics($customer_id);

	$sql = "INSERT INTO " . TB_PREF . "crm_customer_analytics
		(customer_id, period_start, period_end, total_sales, total_payments,
		outstanding_balance, payment_days_avg, customer_lifetime_value)
		VALUES (" . db_escape($analytics['customer_id']) . ", " . db_escape($analytics['period_start']) . ",
		" . db_escape($analytics['period_end']) . ", " . $analytics['total_sales'] . ",
		" . $analytics['total_payments'] . ", " . $analytics['outstanding_balance'] . ",
		" . $analytics['payment_days_avg'] . ", " . $analytics['customer_lifetime_value'] . ")
		ON DUPLICATE KEY UPDATE
		total_sales = VALUES(total_sales),
		total_payments = VALUES(total_payments),
		outstanding_balance = VALUES(outstanding_balance),
		payment_days_avg = VALUES(payment_days_avg),
		customer_lifetime_value = VALUES(customer_lifetime_value)";

	db_query($sql, "Could not update customer analytics");
}

// UI Helper Functions
function customer_types_list($name, $selected_id = null, $submit_on_change = false)
{
	$sql = "SELECT id, type_name FROM " . TB_PREF . "fa_crm_customer_types WHERE inactive = 0 ORDER BY type_name";
	return combo_input($name, $selected_id, $sql, 'id', 'type_name',
		array('select_submit' => $submit_on_change, 'async' => false));
}

function customer_types_list_row($label, $name, $selected_id = null, $submit_on_change = false)
{
	echo "<tr><td class='label'>$label</td><td>";
	echo customer_types_list($name, $selected_id, $submit_on_change);
	echo "</td></tr>\n";
}

function customer_segments_list($name, $selected_id = null, $submit_on_change = false)
{
	$sql = "SELECT id, segment_name FROM " . TB_PREF . "crm_customer_segments WHERE inactive = 0 ORDER BY segment_name";
	return combo_input($name, $selected_id, $sql, 'id', 'segment_name',
		array('select_submit' => $submit_on_change, 'async' => false));
}

function customer_segments_list_row($label, $name, $selected_id = null, $submit_on_change = false)
{
	echo "<tr><td class='label'>$label</td><td>";
	echo customer_segments_list($name, $selected_id, $submit_on_change);
	echo "</td></tr>\n";
}

function contact_roles_list($name, $selected_id = null, $submit_on_change = false)
{
	$sql = "SELECT id, role_name FROM " . TB_PREF . "crm_contact_roles WHERE inactive = 0 ORDER BY role_name";
	return combo_input($name, $selected_id, $sql, 'id', 'role_name',
		array('select_submit' => $submit_on_change, 'async' => false));
}

function contact_roles_list_row($label, $name, $selected_id = null, $submit_on_change = false)
{
	echo "<tr><td class='label'>$label</td><td>";
	echo contact_roles_list($name, $selected_id, $submit_on_change);
	echo "</td></tr>\n";
}

function territories_list($name, $selected_id = null, $submit_on_change = false)
{
	$sql = "SELECT id, territory_name FROM " . TB_PREF . "fa_crm_territories WHERE inactive = 0 ORDER BY territory_name";
	return combo_input($name, $selected_id, $sql, 'id', 'territory_name',
		array('select_submit' => $submit_on_change, 'async' => false));
}

function territories_list_row($label, $name, $selected_id = null, $submit_on_change = false)
{
	echo "<tr><td class='label'>$label</td><td>";
	echo territories_list($name, $selected_id, $submit_on_change);
	echo "</td></tr>\n";
}

function preferred_contact_method_row($label, $name, $selected = null)
{
	global $Ajax;

	echo "<tr><td class='label'>$label</td><td>";
	echo "<select name='$name' id='$name'>";

	$methods = array('email' => _('Email'), 'phone' => _('Phone'), 'mail' => _('Mail'));
	foreach ($methods as $value => $display) {
		echo "<option value='$value'" . ($selected == $value ? " selected" : "") . ">$display</option>";
	}

	echo "</select></td></tr>\n";

	$Ajax->addUpdate($name, $name, $selected);
}

function credit_rating_row($label, $name, $selected = null)
{
	global $Ajax;

	echo "<tr><td class='label'>$label</td><td>";
	echo "<select name='$name' id='$name'>";

	$ratings = array('excellent' => _('Excellent'), 'good' => _('Good'), 'fair' => _('Fair'), 'poor' => _('Poor'));
	foreach ($ratings as $value => $display) {
		echo "<option value='$value'" . ($selected == $value ? " selected" : "") . ">$display</option>";
	}

	echo "</select></td></tr>\n";

	$Ajax->addUpdate($name, $name, $selected);
}

function display_customer_contacts($customer_id)
{
	$sql = "SELECT cc.*, cr.role_name
	FROM " . TB_PREF . "custcontacts cc
	LEFT JOIN " . TB_PREF . "crm_contact_roles cr ON cc.role_id = cr.id
	WHERE cc.debtorno = " . db_escape($customer_id) . "
	ORDER BY cc.contid";

	$result = db_query($sql, "Could not get customer contacts");

	if (db_num_rows($result) == 0) {
		echo "<p>" . _("No contacts defined for this customer") . "</p>";
		return;
	}

	start_table(TABLESTYLE);
	table_header(array(_("Name"), _("Role"), _("Phone"), _("Email"), _("Notes"), ""));

	while ($contact = db_fetch($result)) {
		start_row();
		label_cell($contact['contactname']);
		label_cell($contact['role_name'] ?: $contact['role']);
		label_cell($contact['phoneno']);
		email_cell($contact['email']);
		label_cell($contact['notes']);
		edit_button_cell("Edit" . $contact['contid'], _("Edit"));
		end_row();
	}

	end_table();
}

function display_customer_opportunities($customer_id)
{
	$result = get_customer_opportunities($customer_id);

	if (db_num_rows($result) == 0) {
		echo "<p>" . _("No sales opportunities for this customer") . "</p>";
		return;
	}

	start_table(TABLESTYLE);
	table_header(array(_("Opportunity"), _("Type"), _("Status"), _("Value"), _("Probability"), _("Close Date"), ""));

	while ($opp = db_fetch($result)) {
		start_row();
		label_cell($opp['opportunity_name']);
		label_cell($opp['opportunity_type']);
		label_cell($opp['status']);
		amount_cell($opp['estimated_value']);
		label_cell($opp['probability'] . '%');
		label_cell(sql2date($opp['expected_close_date']));
		edit_button_cell("EditOpp" . $opp['id'], _("Edit"));
		end_row();
	}

	end_table();
}

// ============================================================================
// EMAIL ACCOUNT MANAGEMENT FUNCTIONS
// ============================================================================

function add_email_account($account_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_email_accounts (
		account_name, email_address, server_host, server_port, encryption,
		username, password, auto_import, import_frequency, is_active, created_at
	) VALUES (
		" . db_escape($account_data['account_name']) . ",
		" . db_escape($account_data['email_address']) . ",
		" . db_escape($account_data['server_host']) . ",
		" . (int)$account_data['server_port'] . ",
		" . db_escape($account_data['encryption']) . ",
		" . db_escape($account_data['username']) . ",
		" . db_escape($account_data['password']) . ",
		" . (int)$account_data['auto_import'] . ",
		" . (int)$account_data['import_frequency'] . ",
		" . (int)$account_data['is_active'] . ",
		NOW()
	)";

	if (db_query($sql)) {
		return db_insert_id();
	}

	return false;
}

function update_email_account($account_id, $account_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_email_accounts SET
		account_name = " . db_escape($account_data['account_name']) . ",
		email_address = " . db_escape($account_data['email_address']) . ",
		server_host = " . db_escape($account_data['server_host']) . ",
		server_port = " . (int)$account_data['server_port'] . ",
		encryption = " . db_escape($account_data['encryption']) . ",
		username = " . db_escape($account_data['username']);

	if (!empty($account_data['password'])) {
		$sql .= ", password = " . db_escape($account_data['password']);
	}

	$sql .= ", auto_import = " . (int)$account_data['auto_import'] . ",
		import_frequency = " . (int)$account_data['import_frequency'] . ",
		is_active = " . (int)$account_data['is_active'];

	if (!empty($account_data['last_import'])) {
		$sql .= ", last_import = " . db_escape($account_data['last_import']);
	}

	$sql .= " WHERE id = " . (int)$account_id;

	return db_query($sql);
}

function delete_email_account($account_id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_email_accounts WHERE id = " . (int)$account_id;
	return db_query($sql);
}

function get_email_account($account_id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_email_accounts WHERE id = " . (int)$account_id;
	$result = db_query($sql);
	return db_fetch($result);
}

function get_email_accounts($active_only = true)
{
	$where = $active_only ? "WHERE is_active = 1" : "";
	$sql = "SELECT * FROM " . TB_PREF . "crm_email_accounts $where ORDER BY account_name";
	return db_query($sql);
}

function can_delete_email_account($account_id)
{
	// Check if account has associated communications
	$sql = "SELECT COUNT(*) FROM " . TB_PREF . "fa_crm_communications WHERE email_account_id = " . (int)$account_id;
	$result = db_query($sql);
	$row = db_fetch($result);
	return $row[0] == 0;
}

function test_email_connection($connection_data)
{
	// Test IMAP connection
	$hostname = $connection_data['server_host'];
	$port = $connection_data['server_port'];
	$encryption = $connection_data['encryption'];
	$username = $connection_data['username'];
	$password = $connection_data['password'];

	// Build connection string
	$connection_string = "{" . $hostname . ":" . $port;

	switch ($encryption) {
		case 'ssl':
			$connection_string .= "/ssl";
			break;
		case 'tls':
			$connection_string .= "/tls";
			break;
	}

	$connection_string .= "}INBOX";

	// Test connection
	$imap = @imap_open($connection_string, $username, $password, OP_READONLY, 1);

	if ($imap) {
		imap_close($imap);
		return ['success' => true];
	} else {
		return ['success' => false, 'error' => imap_last_error()];
	}
}

// ============================================================================
// OPPORTUNITIES FUNCTIONS
// ============================================================================

function add_opportunity($customer_id, $name, $description, $stage, $amount, $probability, $expected_close_date, $assigned_to, $source)
{
	$sql = "INSERT INTO " . TB_PREF . "fa_crm_opportunities
		(customer_id, name, description, stage, amount, probability, expected_close_date, assigned_to, source, created_at)
		VALUES (" . db_escape($customer_id) . ", " . db_escape($name) . ", " . db_escape($description) . ",
		" . db_escape($stage) . ", " . (float)$amount . ", " . (float)$probability . ",
		" . db_escape($expected_close_date) . ", " . db_escape($assigned_to) . ", " . db_escape($source) . ", NOW())";

	db_query($sql, "Could not add opportunity");
	return db_insert_id();
}

function update_opportunity($id, $customer_id, $name, $description, $stage, $amount, $probability, $expected_close_date, $assigned_to, $source)
{
	$sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
		customer_id = " . db_escape($customer_id) . ",
		name = " . db_escape($name) . ",
		description = " . db_escape($description) . ",
		stage = " . db_escape($stage) . ",
		amount = " . (float)$amount . ",
		probability = " . (float)$probability . ",
		expected_close_date = " . db_escape($expected_close_date) . ",
		assigned_to = " . db_escape($assigned_to) . ",
		source = " . db_escape($source) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update opportunity");
}

function delete_opportunity($id)
{
	$sql = "DELETE FROM " . TB_PREF . "fa_crm_opportunities WHERE id = " . (int)$id;
	db_query($sql, "Could not delete opportunity");
}

function get_opportunities($active_only = true)
{
	$sql = "SELECT o.*, d.name as customer_name, u.user_id as assigned_to_name
		FROM " . TB_PREF . "fa_crm_opportunities o
		JOIN " . TB_PREF . "debtors_master d ON o.customer_id = d.debtor_no
		LEFT JOIN " . TB_PREF . "users u ON o.assigned_to = u.id";

	if ($active_only) {
		$sql .= " WHERE o.stage NOT IN ('closed_won', 'closed_lost')";
	}

	$sql .= " ORDER BY o.expected_close_date ASC";

	$result = db_query($sql, "Could not get opportunities");
	return $result;
}

function get_opportunity($id)
{
	$sql = "SELECT o.*, d.name as customer_name, u.user_id as assigned_to_name
		FROM " . TB_PREF . "fa_crm_opportunities o
		JOIN " . TB_PREF . "debtors_master d ON o.customer_id = d.debtor_no
		LEFT JOIN " . TB_PREF . "users u ON o.assigned_to = u.id
		WHERE o.id = " . (int)$id;

	$result = db_query($sql, "Could not get opportunity");
	return db_fetch($result);
}

// ============================================================================
// MEETING ROOMS FUNCTIONS
// ============================================================================

function add_meeting_room($room_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meeting_rooms
		(room_name, room_type, location, capacity, equipment, phone_number, conference_url, active)
		VALUES (" . db_escape($room_data['room_name']) . ", " . db_escape($room_data['room_type']) . ",
		" . db_escape($room_data['location']) . ", " . (int)$room_data['capacity'] . ",
		" . db_escape($room_data['equipment']) . ", " . db_escape($room_data['phone_number']) . ",
		" . db_escape($room_data['conference_url']) . ", " . (int)$room_data['active'] . ")";

	db_query($sql, "Could not add meeting room");
	return db_insert_id();
}

function update_meeting_room($id, $room_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_rooms SET
		room_name = " . db_escape($room_data['room_name']) . ",
		room_type = " . db_escape($room_data['room_type']) . ",
		location = " . db_escape($room_data['location']) . ",
		capacity = " . (int)$room_data['capacity'] . ",
		equipment = " . db_escape($room_data['equipment']) . ",
		phone_number = " . db_escape($room_data['phone_number']) . ",
		conference_url = " . db_escape($room_data['conference_url']) . ",
		active = " . (int)$room_data['active'] . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting room");
}

function delete_meeting_room($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meeting_rooms WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting room");
}

function get_meeting_rooms($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_meeting_rooms";
	if ($active_only) {
		$sql .= " WHERE active = 1";
	}
	$sql .= " ORDER BY room_name";

	$result = db_query($sql, "Could not get meeting rooms");
	return $result;
}

function get_meeting_room($id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_meeting_rooms WHERE id = " . (int)$id;
	$result = db_query($sql, "Could not get meeting room");
	return db_fetch($result);
}

// ============================================================================
// MEETINGS FUNCTIONS
// ============================================================================

function add_meeting($meeting_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meetings
		(meeting_name, meeting_type, description, start_date, end_date, duration_minutes, time_zone,
		location_type, room_id, custom_location, phone_number, conference_url, meeting_url,
		dial_in_number, access_code, host_pin, debtor_no, opportunity_id, project_id, quote_id,
		campaign_id, agenda, preparation_notes, status, priority, assigned_to, created_by,
		is_recurring, recurrence_id, reminder_minutes_before, ics_uid, external_id)
		VALUES (" . db_escape($meeting_data['meeting_name']) . ", " . db_escape($meeting_data['meeting_type']) . ",
		" . db_escape($meeting_data['description']) . ", " . db_escape($meeting_data['start_date']) . ",
		" . db_escape($meeting_data['end_date']) . ", " . (int)$meeting_data['duration_minutes'] . ",
		" . db_escape($meeting_data['time_zone']) . ", " . db_escape($meeting_data['location_type']) . ",
		" . db_escape($meeting_data['room_id']) . ", " . db_escape($meeting_data['custom_location']) . ",
		" . db_escape($meeting_data['phone_number']) . ", " . db_escape($meeting_data['conference_url']) . ",
		" . db_escape($meeting_data['meeting_url']) . ", " . db_escape($meeting_data['dial_in_number']) . ",
		" . db_escape($meeting_data['access_code']) . ", " . db_escape($meeting_data['host_pin']) . ",
		" . db_escape($meeting_data['debtor_no']) . ", " . db_escape($meeting_data['opportunity_id']) . ",
		" . db_escape($meeting_data['project_id']) . ", " . db_escape($meeting_data['quote_id']) . ",
		" . db_escape($meeting_data['campaign_id']) . ", " . db_escape($meeting_data['agenda']) . ",
		" . db_escape($meeting_data['preparation_notes']) . ", " . db_escape($meeting_data['status']) . ",
		" . db_escape($meeting_data['priority']) . ", " . db_escape($meeting_data['assigned_to']) . ",
		" . db_escape($meeting_data['created_by']) . ", " . (int)$meeting_data['is_recurring'] . ",
		" . db_escape($meeting_data['recurrence_id']) . ", " . (int)$meeting_data['reminder_minutes_before'] . ",
		" . db_escape($meeting_data['ics_uid']) . ", " . db_escape($meeting_data['external_id']) . ")";

	db_query($sql, "Could not add meeting");
	return db_insert_id();
}

function update_meeting($id, $meeting_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meetings SET
		meeting_name = " . db_escape($meeting_data['meeting_name']) . ",
		meeting_type = " . db_escape($meeting_data['meeting_type']) . ",
		description = " . db_escape($meeting_data['description']) . ",
		start_date = " . db_escape($meeting_data['start_date']) . ",
		end_date = " . db_escape($meeting_data['end_date']) . ",
		duration_minutes = " . (int)$meeting_data['duration_minutes'] . ",
		time_zone = " . db_escape($meeting_data['time_zone']) . ",
		location_type = " . db_escape($meeting_data['location_type']) . ",
		room_id = " . db_escape($meeting_data['room_id']) . ",
		custom_location = " . db_escape($meeting_data['custom_location']) . ",
		phone_number = " . db_escape($meeting_data['phone_number']) . ",
		conference_url = " . db_escape($meeting_data['conference_url']) . ",
		meeting_url = " . db_escape($meeting_data['meeting_url']) . ",
		dial_in_number = " . db_escape($meeting_data['dial_in_number']) . ",
		access_code = " . db_escape($meeting_data['access_code']) . ",
		host_pin = " . db_escape($meeting_data['host_pin']) . ",
		debtor_no = " . db_escape($meeting_data['debtor_no']) . ",
		opportunity_id = " . db_escape($meeting_data['opportunity_id']) . ",
		project_id = " . db_escape($meeting_data['project_id']) . ",
		quote_id = " . db_escape($meeting_data['quote_id']) . ",
		campaign_id = " . db_escape($meeting_data['campaign_id']) . ",
		agenda = " . db_escape($meeting_data['agenda']) . ",
		preparation_notes = " . db_escape($meeting_data['preparation_notes']) . ",
		meeting_outcome = " . db_escape($meeting_data['meeting_outcome']) . ",
		follow_up_actions = " . db_escape($meeting_data['follow_up_actions']) . ",
		status = " . db_escape($meeting_data['status']) . ",
		priority = " . db_escape($meeting_data['priority']) . ",
		assigned_to = " . db_escape($meeting_data['assigned_to']) . ",
		is_recurring = " . (int)$meeting_data['is_recurring'] . ",
		recurrence_id = " . db_escape($meeting_data['recurrence_id']) . ",
		reminder_minutes_before = " . (int)$meeting_data['reminder_minutes_before'] . ",
		ics_uid = " . db_escape($meeting_data['ics_uid']) . ",
		external_id = " . db_escape($meeting_data['external_id']) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting");
}

function delete_meeting($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meetings WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting");
}

function get_meetings($filters = array(), $limit = null, $offset = 0)
{
	$sql = "SELECT m.*, d.name as customer_name, r.room_name, u.user_id as assigned_to_name,
		o.opportunity_name, c.campaign_name
		FROM " . TB_PREF . "crm_meetings m
		LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
		LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
		LEFT JOIN " . TB_PREF . "users u ON m.assigned_to = u.id
		LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON m.opportunity_id = o.id
		LEFT JOIN " . TB_PREF . "crm_campaigns c ON m.campaign_id = c.id
		WHERE 1=1";

	// Apply filters
	if (!empty($filters['debtor_no'])) {
		$sql .= " AND m.debtor_no = " . db_escape($filters['debtor_no']);
	}
	if (!empty($filters['assigned_to'])) {
		$sql .= " AND m.assigned_to = " . db_escape($filters['assigned_to']);
	}
	if (!empty($filters['status'])) {
		$sql .= " AND m.status = " . db_escape($filters['status']);
	}
	if (!empty($filters['start_date'])) {
		$sql .= " AND m.start_date >= " . db_escape($filters['start_date']);
	}
	if (!empty($filters['end_date'])) {
		$sql .= " AND m.end_date <= " . db_escape($filters['end_date']);
	}
	if (!empty($filters['opportunity_id'])) {
		$sql .= " AND m.opportunity_id = " . (int)$filters['opportunity_id'];
	}

	$sql .= " ORDER BY m.start_date ASC";

	if ($limit) {
		$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
	}

	$result = db_query($sql, "Could not get meetings");
	return $result;
}

function get_meeting($id)
{
	$sql = "SELECT m.*, d.name as customer_name, r.room_name, u.user_id as assigned_to_name,
		o.opportunity_name, c.campaign_name
		FROM " . TB_PREF . "crm_meetings m
		LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
		LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
		LEFT JOIN " . TB_PREF . "users u ON m.assigned_to = u.id
		LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON m.opportunity_id = o.id
		LEFT JOIN " . TB_PREF . "crm_campaigns c ON m.campaign_id = c.id
		WHERE m.id = " . (int)$id;

	$result = db_query($sql, "Could not get meeting");
	return db_fetch($result);
}

function get_upcoming_meetings($days_ahead = 7, $user_id = null)
{
	$end_date = date('Y-m-d H:i:s', strtotime("+{$days_ahead} days"));

	$sql = "SELECT m.*, d.name as customer_name, r.room_name
		FROM " . TB_PREF . "crm_meetings m
		LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
		LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
		WHERE m.start_date <= " . db_escape($end_date) . "
		AND m.status IN ('planned', 'confirmed')
		AND m.start_date >= NOW()";

	if ($user_id) {
		$sql .= " AND (m.assigned_to = " . db_escape($user_id) . "
			OR m.id IN (
				SELECT meeting_id FROM " . TB_PREF . "crm_meeting_attendees
				WHERE attendee_type = 'employee' AND employee_id = " . (int)$user_id . "
			))";
	}

	$sql .= " ORDER BY m.start_date ASC";

	$result = db_query($sql, "Could not get upcoming meetings");
	return $result;
}

// ============================================================================
// MEETING ATTENDEES FUNCTIONS
// ============================================================================

function add_meeting_attendee($meeting_id, $attendee_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meeting_attendees
		(meeting_id, attendee_type, employee_id, contact_id, external_name, external_email,
		external_phone, attendee_role, response_status, notes)
		VALUES (" . (int)$meeting_id . ", " . db_escape($attendee_data['attendee_type']) . ",
		" . db_escape($attendee_data['employee_id']) . ", " . db_escape($attendee_data['contact_id']) . ",
		" . db_escape($attendee_data['external_name']) . ", " . db_escape($attendee_data['external_email']) . ",
		" . db_escape($attendee_data['external_phone']) . ", " . db_escape($attendee_data['attendee_role']) . ",
		" . db_escape($attendee_data['response_status']) . ", " . db_escape($attendee_data['notes']) . ")";

	db_query($sql, "Could not add meeting attendee");
	return db_insert_id();
}

function update_meeting_attendee($id, $attendee_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_attendees SET
		attendee_type = " . db_escape($attendee_data['attendee_type']) . ",
		employee_id = " . db_escape($attendee_data['employee_id']) . ",
		contact_id = " . db_escape($attendee_data['contact_id']) . ",
		external_name = " . db_escape($attendee_data['external_name']) . ",
		external_email = " . db_escape($attendee_data['external_email']) . ",
		external_phone = " . db_escape($attendee_data['external_phone']) . ",
		attendee_role = " . db_escape($attendee_data['attendee_role']) . ",
		response_status = " . db_escape($attendee_data['response_status']) . ",
		response_date = " . db_escape($attendee_data['response_date']) . ",
		notes = " . db_escape($attendee_data['notes']) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting attendee");
}

function delete_meeting_attendee($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meeting_attendees WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting attendee");
}

function get_meeting_attendees($meeting_id)
{
	$sql = "SELECT ma.*, c.first_name, c.last_name, c.email as contact_email,
		u.user_id as employee_name, u.email as employee_email
		FROM " . TB_PREF . "crm_meeting_attendees ma
		LEFT JOIN " . TB_PREF . "fa_crm_contacts c ON ma.contact_id = c.id
		LEFT JOIN " . TB_PREF . "users u ON ma.employee_id = u.id
		WHERE ma.meeting_id = " . (int)$meeting_id . "
		ORDER BY ma.attendee_role, ma.attendee_type";

	$result = db_query($sql, "Could not get meeting attendees");
	return $result;
}

function update_attendee_response($attendee_id, $response_status)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_attendees SET
		response_status = " . db_escape($response_status) . ",
		response_date = NOW()
	WHERE id = " . (int)$attendee_id;

	db_query($sql, "Could not update attendee response");
}

// ============================================================================
// ICS AND CALENDAR FUNCTIONS
// ============================================================================

function generate_ics_content($meeting)
{
	$ics = "BEGIN:VCALENDAR\r\n";
	$ics .= "VERSION:2.0\r\n";
	$ics .= "PRODID:-//FrontAccounting CRM//EN\r\n";
	$ics .= "BEGIN:VEVENT\r\n";
	$ics .= "UID:" . ($meeting['ics_uid'] ?: uniqid()) . "\r\n";
	$ics .= "DTSTART:" . gmdate('Ymd\THis\Z', strtotime($meeting['start_date'])) . "\r\n";
	$ics .= "DTEND:" . gmdate('Ymd\THis\Z', strtotime($meeting['end_date'])) . "\r\n";
	$ics .= "SUMMARY:" . $meeting['meeting_name'] . "\r\n";

	if ($meeting['description']) {
		$ics .= "DESCRIPTION:" . str_replace(array("\r\n", "\n"), "\\n", $meeting['description']) . "\r\n";
	}

	if ($meeting['location_type'] == 'physical' && $meeting['room_id']) {
		$room = get_meeting_room($meeting['room_id']);
		$ics .= "LOCATION:" . $room['room_name'];
		if ($room['location']) {
			$ics .= " - " . $room['location'];
		}
		$ics .= "\r\n";
	} elseif ($meeting['custom_location']) {
		$ics .= "LOCATION:" . $meeting['custom_location'] . "\r\n";
	}

	// Add organizer
	if ($meeting['assigned_to']) {
		$user = get_user($meeting['assigned_to']);
		if ($user && $user['email']) {
			$ics .= "ORGANIZER;CN=" . $user['user_id'] . ":mailto:" . $user['email'] . "\r\n";
		}
	}

	// Add attendees
	$attendees = get_meeting_attendees($meeting['id']);
	while ($attendee = db_fetch($attendees)) {
		$attendee_email = '';
		$attendee_name = '';

		if ($attendee['attendee_type'] == 'employee' && $attendee['employee_email']) {
			$attendee_email = $attendee['employee_email'];
			$attendee_name = $attendee['employee_name'];
		} elseif ($attendee['attendee_type'] == 'contact' && $attendee['contact_email']) {
			$attendee_email = $attendee['contact_email'];
			$attendee_name = $attendee['first_name'] . ' ' . $attendee['last_name'];
		} elseif ($attendee['attendee_type'] == 'external' && $attendee['external_email']) {
			$attendee_email = $attendee['external_email'];
			$attendee_name = $attendee['external_name'];
		}

		if ($attendee_email) {
			$ics .= "ATTENDEE;CN=" . $attendee_name . ";RSVP=TRUE:mailto:" . $attendee_email . "\r\n";
		}
	}

	$ics .= "STATUS:CONFIRMED\r\n";
	$ics .= "END:VEVENT\r\n";
	$ics .= "END:VCALENDAR\r\n";

	return $ics;
}

function send_meeting_invite($meeting_id, $to_email, $subject = null, $message = null)
{
	$meeting = get_meeting($meeting_id);
	if (!$meeting) {
		return false;
	}

	$ics_content = generate_ics_content($meeting);

	// Create email with ICS attachment
	$boundary = md5(uniqid());

	$headers = array(
		'MIME-Version: 1.0',
		'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
		'From: ' . $_SESSION['wa_current_user']->email,
		'Reply-To: ' . $_SESSION['wa_current_user']->email
	);

	$email_subject = $subject ?: "Meeting Invitation: " . $meeting['meeting_name'];
	$email_message = $message ?: "You are invited to the following meeting:\n\n" . $meeting['meeting_name'];

	$body = "--" . $boundary . "\r\n";
	$body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
	$body .= $email_message . "\r\n\r\n";
	$body .= "--" . $boundary . "\r\n";
	$body .= "Content-Type: text/calendar; charset=UTF-8; method=REQUEST\r\n";
	$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
	$body .= $ics_content . "\r\n";
	$body .= "--" . $boundary . "--";

	return mail($to_email, $email_subject, $body, implode("\r\n", $headers));
}

function parse_ics_file($ics_content)
{
	$events = array();
	$lines = explode("\r\n", $ics_content);
	$current_event = null;
	$in_event = false;

	foreach ($lines as $line) {
		$line = trim($line);

		if ($line == 'BEGIN:VEVENT') {
			$current_event = array();
			$in_event = true;
		} elseif ($line == 'END:VEVENT') {
			if ($current_event) {
				$events[] = $current_event;
			}
			$current_event = null;
			$in_event = false;
		} elseif ($in_event && strpos($line, ':') !== false) {
			list($key, $value) = explode(':', $line, 2);

			// Handle parameters in key
			if (strpos($key, ';') !== false) {
				list($key, $params) = explode(';', $key, 2);
			}

			$current_event[strtolower($key)] = $value;
		}
	}

	return $events;
}

function import_ics_meeting($ics_event, $account_id = null)
{
	$meeting_data = array(
		'meeting_name' => $ics_event['summary'] ?? 'Imported Meeting',
		'meeting_type' => 'meeting',
		'description' => $ics_event['description'] ?? '',
		'start_date' => date('Y-m-d H:i:s', strtotime($ics_event['dtstart'])),
		'end_date' => date('Y-m-d H:i:s', strtotime($ics_event['dtend'])),
		'location_type' => $ics_event['location'] ? 'physical' : 'virtual',
		'custom_location' => $ics_event['location'] ?? '',
		'status' => 'confirmed',
		'priority' => 'normal',
		'ics_uid' => $ics_event['uid'] ?? uniqid(),
		'external_id' => $ics_event['uid'] ?? null,
		'created_by' => $_SESSION['wa_current_user']->loginname
	);

	// Calculate duration
	$start = strtotime($meeting_data['start_date']);
	$end = strtotime($meeting_data['end_date']);
	$meeting_data['duration_minutes'] = round(($end - $start) / 60);

	$meeting_id = add_meeting($meeting_data);

	// Add organizer as attendee if available
	if (isset($ics_event['organizer'])) {
		// Parse organizer email from ORGANIZER field
		if (preg_match('/mailto:(.+)/i', $ics_event['organizer'], $matches)) {
			$organizer_email = $matches[1];
			add_meeting_attendee($meeting_id, array(
				'attendee_type' => 'external',
				'external_email' => $organizer_email,
				'attendee_role' => 'organizer',
				'response_status' => 'accepted'
			));
		}
	}

	return $meeting_id;
}

// ============================================================================
// MEETING ROOMS FUNCTIONS
// ============================================================================

function add_meeting_room($room_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meeting_rooms
		(room_name, room_type, location, capacity, equipment, phone_number, conference_url, active)
		VALUES (" . db_escape($room_data['room_name']) . ", " . db_escape($room_data['room_type']) . ",
		" . db_escape($room_data['location']) . ", " . (int)$room_data['capacity'] . ",
		" . db_escape($room_data['equipment']) . ", " . db_escape($room_data['phone_number']) . ",
		" . db_escape($room_data['conference_url']) . ", " . (int)$room_data['active'] . ")";

	db_query($sql, "Could not add meeting room");
	return db_insert_id();
}

function update_meeting_room($id, $room_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_rooms SET
		room_name = " . db_escape($room_data['room_name']) . ",
		room_type = " . db_escape($room_data['room_type']) . ",
		location = " . db_escape($room_data['location']) . ",
		capacity = " . (int)$room_data['capacity'] . ",
		equipment = " . db_escape($room_data['equipment']) . ",
		phone_number = " . db_escape($room_data['phone_number']) . ",
		conference_url = " . db_escape($room_data['conference_url']) . ",
		active = " . (int)$room_data['active'] . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting room");
}

function delete_meeting_room($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meeting_rooms WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting room");
}

function get_meeting_rooms($active_only = true)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_meeting_rooms";
	if ($active_only) {
		$sql .= " WHERE active = 1";
	}
	$sql .= " ORDER BY room_name";

	$result = db_query($sql, "Could not get meeting rooms");
	return $result;
}

function get_meeting_room($id)
{
	$sql = "SELECT * FROM " . TB_PREF . "crm_meeting_rooms WHERE id = " . (int)$id;
	$result = db_query($sql, "Could not get meeting room");
	return db_fetch($result);
}

// ============================================================================
// MEETINGS FUNCTIONS
// ============================================================================

function add_meeting($meeting_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meetings (
		meeting_name, meeting_type, description, start_date, end_date, duration_minutes, time_zone,
		location_type, room_id, custom_location, phone_number, conference_url, meeting_url,
		dial_in_number, access_code, host_pin, debtor_no, opportunity_id, project_id, quote_id,
		campaign_id, agenda, preparation_notes, status, priority, assigned_to, created_by,
		is_recurring, recurrence_id, reminder_minutes_before, ics_uid, external_id
	) VALUES (
		" . db_escape($meeting_data['meeting_name']) . ",
		" . db_escape($meeting_data['meeting_type']) . ",
		" . db_escape($meeting_data['description']) . ",
		" . db_escape($meeting_data['start_date']) . ",
		" . db_escape($meeting_data['end_date']) . ",
		" . (int)$meeting_data['duration_minutes'] . ",
		" . db_escape($meeting_data['time_zone']) . ",
		" . db_escape($meeting_data['location_type']) . ",
		" . db_escape($meeting_data['room_id']) . ",
		" . db_escape($meeting_data['custom_location']) . ",
		" . db_escape($meeting_data['phone_number']) . ",
		" . db_escape($meeting_data['conference_url']) . ",
		" . db_escape($meeting_data['meeting_url']) . ",
		" . db_escape($meeting_data['dial_in_number']) . ",
		" . db_escape($meeting_data['access_code']) . ",
		" . db_escape($meeting_data['host_pin']) . ",
		" . db_escape($meeting_data['debtor_no']) . ",
		" . db_escape($meeting_data['opportunity_id']) . ",
		" . db_escape($meeting_data['project_id']) . ",
		" . db_escape($meeting_data['quote_id']) . ",
		" . db_escape($meeting_data['campaign_id']) . ",
		" . db_escape($meeting_data['agenda']) . ",
		" . db_escape($meeting_data['preparation_notes']) . ",
		" . db_escape($meeting_data['status']) . ",
		" . db_escape($meeting_data['priority']) . ",
		" . db_escape($meeting_data['assigned_to']) . ",
		" . db_escape($meeting_data['created_by']) . ",
		" . (int)$meeting_data['is_recurring'] . ",
		" . db_escape($meeting_data['recurrence_id']) . ",
		" . (int)$meeting_data['reminder_minutes_before'] . ",
		" . db_escape($meeting_data['ics_uid']) . ",
		" . db_escape($meeting_data['external_id']) . "
	)";

	db_query($sql, "Could not add meeting");
	return db_insert_id();
}

function update_meeting($id, $meeting_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meetings SET
		meeting_name = " . db_escape($meeting_data['meeting_name']) . ",
		meeting_type = " . db_escape($meeting_data['meeting_type']) . ",
		description = " . db_escape($meeting_data['description']) . ",
		start_date = " . db_escape($meeting_data['start_date']) . ",
		end_date = " . db_escape($meeting_data['end_date']) . ",
		duration_minutes = " . (int)$meeting_data['duration_minutes'] . ",
		time_zone = " . db_escape($meeting_data['time_zone']) . ",
		location_type = " . db_escape($meeting_data['location_type']) . ",
		room_id = " . db_escape($meeting_data['room_id']) . ",
		custom_location = " . db_escape($meeting_data['custom_location']) . ",
		phone_number = " . db_escape($meeting_data['phone_number']) . ",
		conference_url = " . db_escape($meeting_data['conference_url']) . ",
		meeting_url = " . db_escape($meeting_data['meeting_url']) . ",
		dial_in_number = " . db_escape($meeting_data['dial_in_number']) . ",
		access_code = " . db_escape($meeting_data['access_code']) . ",
		host_pin = " . db_escape($meeting_data['host_pin']) . ",
		debtor_no = " . db_escape($meeting_data['debtor_no']) . ",
		opportunity_id = " . db_escape($meeting_data['opportunity_id']) . ",
		project_id = " . db_escape($meeting_data['project_id']) . ",
		quote_id = " . db_escape($meeting_data['quote_id']) . ",
		campaign_id = " . db_escape($meeting_data['campaign_id']) . ",
		agenda = " . db_escape($meeting_data['agenda']) . ",
		preparation_notes = " . db_escape($meeting_data['preparation_notes']) . ",
		meeting_outcome = " . db_escape($meeting_data['meeting_outcome']) . ",
		follow_up_actions = " . db_escape($meeting_data['follow_up_actions']) . ",
		status = " . db_escape($meeting_data['status']) . ",
		priority = " . db_escape($meeting_data['priority']) . ",
		assigned_to = " . db_escape($meeting_data['assigned_to']) . ",
		is_recurring = " . (int)$meeting_data['is_recurring'] . ",
		recurrence_id = " . db_escape($meeting_data['recurrence_id']) . ",
		reminder_minutes_before = " . (int)$meeting_data['reminder_minutes_before'] . ",
		ics_uid = " . db_escape($meeting_data['ics_uid']) . ",
		external_id = " . db_escape($meeting_data['external_id']) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting");
}

function delete_meeting($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meetings WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting");
}

function get_meetings($filters = array(), $limit = null, $offset = 0)
{
	$sql = "SELECT m.*,
		d.name as customer_name,
		r.room_name,
		u.user_id as assigned_to_name,
		o.opportunity_name,
		c.campaign_name
	FROM " . TB_PREF . "crm_meetings m
	LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
	LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
	LEFT JOIN " . TB_PREF . "users u ON m.assigned_to = u.id
	LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON m.opportunity_id = o.id
	LEFT JOIN " . TB_PREF . "crm_campaigns c ON m.campaign_id = c.id
	WHERE 1=1";

	// Apply filters
	if (!empty($filters['debtor_no'])) {
		$sql .= " AND m.debtor_no = " . db_escape($filters['debtor_no']);
	}
	if (!empty($filters['assigned_to'])) {
		$sql .= " AND m.assigned_to = " . db_escape($filters['assigned_to']);
	}
	if (!empty($filters['status'])) {
		$sql .= " AND m.status = " . db_escape($filters['status']);
	}
	if (!empty($filters['start_date'])) {
		$sql .= " AND m.start_date >= " . db_escape($filters['start_date']);
	}
	if (!empty($filters['end_date'])) {
		$sql .= " AND m.end_date <= " . db_escape($filters['end_date']);
	}
	if (!empty($filters['meeting_type'])) {
		$sql .= " AND m.meeting_type = " . db_escape($filters['meeting_type']);
	}

	$sql .= " ORDER BY m.start_date ASC";

	if ($limit) {
		$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
	}

	$result = db_query($sql, "Could not get meetings");
	return $result;
}

function get_meeting($id)
{
	$sql = "SELECT m.*,
		d.name as customer_name,
		r.room_name, r.room_type, r.location, r.capacity, r.equipment,
		u.user_id as assigned_to_name,
		o.opportunity_name,
		c.campaign_name
	FROM " . TB_PREF . "crm_meetings m
	LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
	LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
	LEFT JOIN " . TB_PREF . "users u ON m.assigned_to = u.id
	LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON m.opportunity_id = o.id
	LEFT JOIN " . TB_PREF . "crm_campaigns c ON m.campaign_id = c.id
	WHERE m.id = " . (int)$id;

	$result = db_query($sql, "Could not get meeting");
	return db_fetch($result);
}

function get_upcoming_meetings($days_ahead = 7, $user_id = null)
{
	$end_date = date('Y-m-d H:i:s', strtotime("+{$days_ahead} days"));

	$sql = "SELECT m.*,
		d.name as customer_name,
		r.room_name,
		u.user_id as assigned_to_name
	FROM " . TB_PREF . "crm_meetings m
	LEFT JOIN " . TB_PREF . "debtors_master d ON m.debtor_no = d.debtor_no
	LEFT JOIN " . TB_PREF . "crm_meeting_rooms r ON m.room_id = r.id
	LEFT JOIN " . TB_PREF . "users u ON m.assigned_to = u.id
	WHERE m.start_date <= " . db_escape($end_date) . "
	AND m.status IN ('planned', 'confirmed')
	AND m.start_date >= NOW()";

	if ($user_id) {
		$sql .= " AND (m.assigned_to = " . db_escape($user_id) . "
			OR m.id IN (
				SELECT meeting_id FROM " . TB_PREF . "crm_meeting_attendees
				WHERE attendee_type = 'employee' AND employee_id = " . db_escape($user_id) . "
			))";
	}

	$sql .= " ORDER BY m.start_date ASC";

	$result = db_query($sql, "Could not get upcoming meetings");
	return $result;
}

// ============================================================================
// MEETING ATTENDEES FUNCTIONS
// ============================================================================

function add_meeting_attendee($meeting_id, $attendee_data)
{
	$sql = "INSERT INTO " . TB_PREF . "crm_meeting_attendees (
		meeting_id, attendee_type, employee_id, contact_id, external_name,
		external_email, external_phone, attendee_role, response_status, notes
	) VALUES (
		" . (int)$meeting_id . ",
		" . db_escape($attendee_data['attendee_type']) . ",
		" . db_escape($attendee_data['employee_id']) . ",
		" . db_escape($attendee_data['contact_id']) . ",
		" . db_escape($attendee_data['external_name']) . ",
		" . db_escape($attendee_data['external_email']) . ",
		" . db_escape($attendee_data['external_phone']) . ",
		" . db_escape($attendee_data['attendee_role']) . ",
		" . db_escape($attendee_data['response_status']) . ",
		" . db_escape($attendee_data['notes']) . "
	)";

	db_query($sql, "Could not add meeting attendee");
	return db_insert_id();
}

function update_meeting_attendee($id, $attendee_data)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_attendees SET
		attendee_type = " . db_escape($attendee_data['attendee_type']) . ",
		employee_id = " . db_escape($attendee_data['employee_id']) . ",
		contact_id = " . db_escape($attendee_data['contact_id']) . ",
		external_name = " . db_escape($attendee_data['external_name']) . ",
		external_email = " . db_escape($attendee_data['external_email']) . ",
		external_phone = " . db_escape($attendee_data['external_phone']) . ",
		attendee_role = " . db_escape($attendee_data['attendee_role']) . ",
		response_status = " . db_escape($attendee_data['response_status']) . ",
		response_date = NOW(),
		notes = " . db_escape($attendee_data['notes']) . "
	WHERE id = " . (int)$id;

	db_query($sql, "Could not update meeting attendee");
}

function delete_meeting_attendee($id)
{
	$sql = "DELETE FROM " . TB_PREF . "crm_meeting_attendees WHERE id = " . (int)$id;
	db_query($sql, "Could not delete meeting attendee");
}

function get_meeting_attendees($meeting_id)
{
	$sql = "SELECT ma.*,
		u.user_id as employee_name,
		CONCAT(cc.first_name, ' ', cc.last_name) as contact_name,
		cc.email as contact_email,
		cc.phone as contact_phone
	FROM " . TB_PREF . "crm_meeting_attendees ma
	LEFT JOIN " . TB_PREF . "users u ON ma.employee_id = u.id AND ma.attendee_type = 'employee'
	LEFT JOIN " . TB_PREF . "fa_crm_contacts cc ON ma.contact_id = cc.id AND ma.attendee_type = 'contact'
	WHERE ma.meeting_id = " . (int)$meeting_id . "
	ORDER BY ma.attendee_role ASC, ma.attendee_type ASC";

	$result = db_query($sql, "Could not get meeting attendees");
	return $result;
}

function update_attendee_response($attendee_id, $response_status)
{
	$sql = "UPDATE " . TB_PREF . "crm_meeting_attendees SET
		response_status = " . db_escape($response_status) . ",
		response_date = NOW()
	WHERE id = " . (int)$attendee_id;

	db_query($sql, "Could not update attendee response");
}

// ============================================================================
// ICS AND CALENDAR FUNCTIONS
// ============================================================================

function generate_ics_content($meeting)
{
	$ics = "BEGIN:VCALENDAR\r\n";
	$ics .= "VERSION:2.0\r\n";
	$ics .= "PRODID:-//FrontAccounting CRM//EN\r\n";
	$ics .= "METHOD:REQUEST\r\n";
	$ics .= "BEGIN:VEVENT\r\n";

	// UID
	$ics .= "UID:" . ($meeting['ics_uid'] ?: uniqid('FA-CRM-', true)) . "\r\n";

	// Basic event details
	$ics .= "SUMMARY:" . $meeting['meeting_name'] . "\r\n";
	$ics .= "DESCRIPTION:" . str_replace(array("\r\n", "\n"), "\\n", $meeting['description']) . "\r\n";

	// Dates (convert to UTC)
	$start_utc = date('Ymd\THis\Z', strtotime($meeting['start_date']));
	$end_utc = date('Ymd\THis\Z', strtotime($meeting['end_date']));
	$ics .= "DTSTART:" . $start_utc . "\r\n";
	$ics .= "DTEND:" . $end_utc . "\r\n";

	// Location
	if ($meeting['location_type'] == 'physical' && $meeting['room_name']) {
		$ics .= "LOCATION:" . $meeting['room_name'];
		if ($meeting['location']) {
			$ics .= " - " . $meeting['location'];
		}
		$ics .= "\r\n";
	} elseif ($meeting['location_type'] == 'virtual' && $meeting['conference_url']) {
		$ics .= "LOCATION:" . $meeting['conference_url'] . "\r\n";
	} elseif ($meeting['location_type'] == 'phone' && $meeting['phone_number']) {
		$ics .= "LOCATION:" . $meeting['phone_number'] . "\r\n";
	}

	// Organizer
	if ($meeting['assigned_to_name']) {
		$ics .= "ORGANIZER;CN=" . $meeting['assigned_to_name'] . ":mailto:" . $meeting['assigned_to'] . "\r\n";
	}

	// Attendees
	$attendees = get_meeting_attendees($meeting['id']);
	while ($attendee = db_fetch($attendees)) {
		$attendee_name = '';
		$attendee_email = '';

		if ($attendee['attendee_type'] == 'employee' && $attendee['employee_name']) {
			$attendee_name = $attendee['employee_name'];
			$attendee_email = $attendee['employee_id']; // This should be email, but we need to get it from users table
		} elseif ($attendee['attendee_type'] == 'contact' && $attendee['contact_name']) {
			$attendee_name = $attendee['contact_name'];
			$attendee_email = $attendee['contact_email'];
		} elseif ($attendee['attendee_type'] == 'external') {
			$attendee_name = $attendee['external_name'];
			$attendee_email = $attendee['external_email'];
		}

		if ($attendee_email) {
			$role = $attendee['attendee_role'] == 'required' ? 'REQ-PARTICIPANT' : 'OPT-PARTICIPANT';
			$ics .= "ATTENDEE;ROLE=" . $role . ";CN=" . $attendee_name . ":mailto:" . $attendee_email . "\r\n";
		}
	}

	// Status
	$status_map = array(
		'planned' => 'TENTATIVE',
		'confirmed' => 'CONFIRMED',
		'cancelled' => 'CANCELLED',
		'completed' => 'CONFIRMED'
	);
	if (isset($status_map[$meeting['status']])) {
		$ics .= "STATUS:" . $status_map[$meeting['status']] . "\r\n";
	}

	// Priority
	$priority_map = array('low' => 9, 'normal' => 5, 'high' => 1, 'urgent' => 1);
	if (isset($priority_map[$meeting['priority']])) {
		$ics .= "PRIORITY:" . $priority_map[$meeting['priority']] . "\r\n";
	}

	// Timestamps
	$created = date('Ymd\THis\Z', strtotime($meeting['created_at']));
	$ics .= "CREATED:" . $created . "\r\n";
	$ics .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";

	$ics .= "END:VEVENT\r\n";
	$ics .= "END:VCALENDAR\r\n";

	return $ics;
}

function parse_ics_file($ics_content)
{
	$events = array();
	$lines = explode("\n", $ics_content);

	$current_event = null;
	$in_event = false;

	foreach ($lines as $line) {
		$line = trim($line);

		if ($line == 'BEGIN:VEVENT') {
			$current_event = array();
			$in_event = true;
		} elseif ($line == 'END:VEVENT') {
			if ($current_event) {
				$events[] = $current_event;
			}
			$current_event = null;
			$in_event = false;
		} elseif ($in_event && strpos($line, ':') !== false) {
			list($key, $value) = explode(':', $line, 2);

			// Handle parameters in key
			if (strpos($key, ';') !== false) {
				list($key, $params) = explode(';', $key, 2);
			}

			switch ($key) {
				case 'SUMMARY':
					$current_event['meeting_name'] = $value;
					break;
				case 'DESCRIPTION':
					$current_event['description'] = str_replace('\\n', "\n", $value);
					break;
				case 'DTSTART':
					$current_event['start_date'] = $value;
					break;
				case 'DTEND':
					$current_event['end_date'] = $value;
					break;
				case 'LOCATION':
					$current_event['location'] = $value;
					break;
				case 'UID':
					$current_event['ics_uid'] = $value;
					break;
				case 'STATUS':
					$status_map = array(
						'TENTATIVE' => 'planned',
						'CONFIRMED' => 'confirmed',
						'CANCELLED' => 'cancelled'
					);
					$current_event['status'] = isset($status_map[$value]) ? $status_map[$value] : 'planned';
					break;
			}
		}
	}

	return $events;
}
// ============================================================================
// ADDITIONAL HELPER FUNCTIONS FOR FA_CRM MODULE
// ============================================================================

/**
 * Get CRM customer count
 */
function get_crm_customer_count() {
    $sql = "SELECT COUNT(*) as count FROM " . TB_PREF . "fa_crm_customers WHERE inactive = 0";
    $result = db_query($sql);
    $row = db_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get CRM opportunity count by status
 */
function get_crm_opportunity_count($status = 'active') {
    $sql = "SELECT COUNT(*) as count FROM " . TB_PREF . "fa_crm_opportunities WHERE inactive = 0";
    if ($status == 'active') {
        $sql .= " AND status NOT IN ('won', 'lost')";
    } elseif ($status) {
        $sql .= " AND status = " . db_escape($status);
    }
    $result = db_query($sql);
    $row = db_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get CRM follow-up count
 */
function get_crm_followup_count() {
    $sql = "SELECT COUNT(*) as count FROM " . TB_PREF . "fa_crm_communications 
            WHERE follow_up_required = 1 
            AND follow_up_date <= '" . date('Y-m-d') . "'
            AND status != 'completed'";
    $result = db_query($sql);
    $row = db_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get CRM communication count for today
 */
function get_crm_communication_count_today() {
    $sql = "SELECT COUNT(*) as count FROM " . TB_PREF . "fa_crm_communications 
            WHERE DATE(created_at) = CURDATE()";
    $result = db_query($sql);
    $row = db_fetch_assoc($result);
    return $row['count'] ?? 0;
}

/**
 * Get CRM customers with search and filter
 */
function get_crm_customers($search = '', $filter_type = '') {
    $sql = "SELECT c.*, d.name, ct.name as type_name, t.name as territory_name
            FROM " . TB_PREF . "fa_crm_customers c
            LEFT JOIN " . TB_PREF . "debtors_master d ON c.debtor_no = d.debtor_no
            LEFT JOIN " . TB_PREF . "fa_crm_customer_types ct ON c.customer_type_id = ct.id
            LEFT JOIN " . TB_PREF . "fa_crm_territories t ON c.territory_id = t.id
            WHERE c.inactive = 0";
    
    if ($search) {
        $sql .= " AND (d.name LIKE " . db_escape('%' . $search . '%') . "
                  OR c.industry LIKE " . db_escape('%' . $search . '%') . "
                  OR c.account_manager LIKE " . db_escape('%' . $search . '%') . ")";
    }
    
    if ($filter_type) {
        $sql .= " AND c.customer_type_id = " . db_escape($filter_type);
    }
    
    $sql .= " ORDER BY d.name";
    
    return db_query($sql, "Could not get CRM customers");
}

/**
 * Get CRM customer by debtor_no
 */
function get_crm_customer($debtor_no) {
    $sql = "SELECT c.*, d.name, ct.name as type_name, t.name as territory_name
            FROM " . TB_PREF . "fa_crm_customers c
            LEFT JOIN " . TB_PREF . "debtors_master d ON c.debtor_no = d.debtor_no
            LEFT JOIN " . TB_PREF . "fa_crm_customer_types ct ON c.customer_type_id = ct.id
            LEFT JOIN " . TB_PREF . "fa_crm_territories t ON c.territory_id = t.id
            WHERE c.debtor_no = " . db_escape($debtor_no);
    
    $result = db_query($sql, "Could not get CRM customer");
    return db_fetch_assoc($result);
}

/**
 * Get CRM customer types
 */
function get_crm_customer_types() {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_customer_types 
            WHERE inactive = 0 ORDER BY sort_order, name";
    return db_query($sql, "Could not get customer types");
}

/**
 * Get CRM territories
 */
function get_crm_territories() {
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_territories 
            WHERE inactive = 0 ORDER BY sort_order, name";
    return db_query($sql, "Could not get territories");
}

/**
 * Get CRM opportunities
 */
function get_crm_opportunities($status = '') {
    $sql = "SELECT o.*, d.name as customer_name
            FROM " . TB_PREF . "fa_crm_opportunities o
            LEFT JOIN " . TB_PREF . "debtors_master d ON o.debtor_no = d.debtor_no
            WHERE o.inactive = 0";
    
    if ($status) {
        $sql .= " AND o.status = " . db_escape($status);
    }
    
    $sql .= " ORDER BY o.expected_close_date";
    
    return db_query($sql, "Could not get opportunities");
}

/**
 * Get CRM opportunity by ID
 */
function get_crm_opportunity($id) {
    $sql = "SELECT o.*, d.name as customer_name
            FROM " . TB_PREF . "fa_crm_opportunities o
            LEFT JOIN " . TB_PREF . "debtors_master d ON o.debtor_no = d.debtor_no
            WHERE o.id = " . db_escape($id);
    
    $result = db_query($sql, "Could not get opportunity");
    return db_fetch_assoc($result);
}

/**
 * Insert CRM opportunity
 */
function insert_crm_opportunity($data) {
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_opportunities 
            (opportunity_name, debtor_no, opportunity_type, status, stage, source, 
             estimated_value, probability, expected_close_date, notes, assigned_to, created_at)
            VALUES (
                " . db_escape($data['opportunity_name']) . ",
                " . db_escape($data['debtor_no']) . ",
                " . db_escape($data['opportunity_type'] ?? '') . ",
                " . db_escape($data['status'] ?? 'prospecting') . ",
                " . db_escape($data['stage'] ?? 'qualification') . ",
                " . db_escape($data['source'] ?? '') . ",
                " . db_escape($data['estimated_value'] ?? 0) . ",
                " . db_escape($data['probability'] ?? 0) . ",
                " . db_escape($data['expected_close_date'] ?? '') . ",
                " . db_escape($data['notes'] ?? '') . ",
                " . db_escape($data['assigned_to'] ?? '') . ",
                NOW()
            )";
    
    db_query($sql, "Could not insert opportunity");
    return db_insert_id();
}

/**
 * Update CRM opportunity
 */
function update_crm_opportunity($id, $data) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
            opportunity_name = " . db_escape($data['opportunity_name']) . ",
            debtor_no = " . db_escape($data['debtor_no']) . ",
            opportunity_type = " . db_escape($data['opportunity_type'] ?? '') . ",
            status = " . db_escape($data['status'] ?? 'prospecting') . ",
            stage = " . db_escape($data['stage'] ?? 'qualification') . ",
            estimated_value = " . db_escape($data['estimated_value'] ?? 0) . ",
            probability = " . db_escape($data['probability'] ?? 0) . ",
            expected_close_date = " . db_escape($data['expected_close_date'] ?? '') . ",
            notes = " . db_escape($data['notes'] ?? '') . ",
            assigned_to = " . db_escape($data['assigned_to'] ?? '') . "
            WHERE id = " . db_escape($id);
    
    db_query($sql, "Could not update opportunity");
}

/**
 * Update opportunity stage
 */
function update_crm_opportunity_stage($id, $stage) {
    $sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
            stage = " . db_escape($stage) . "
            WHERE id = " . db_escape($id);
    
    db_query($sql, "Could not update opportunity stage");
}

/**
 * Get CRM communications
 */
function get_crm_communications($filters = array()) {
    $sql = "SELECT c.*, d.name as customer_name
            FROM " . TB_PREF . "fa_crm_communications c
            LEFT JOIN " . TB_PREF . "debtors_master d ON c.debtor_no = d.debtor_no
            WHERE 1=1";
    
    if (!empty($filters['debtor_no'])) {
        $sql .= " AND c.debtor_no = " . db_escape($filters['debtor_no']);
    }
    
    $sql .= " ORDER BY c.created_at DESC LIMIT 100";
    
    return db_query($sql, "Could not get communications");
}

/**
 * Insert CRM communication
 */
function insert_crm_communication($data) {
    $user_id = isset($_SESSION['wa_current_user']) ? $_SESSION['wa_current_user']->user : 'system';
    
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_communications 
            (debtor_no, contact_id, communication_type, direction, subject, message,
             follow_up_required, follow_up_date, notes, created_by, created_at)
            VALUES (
                " . db_escape($data['debtor_no'] ?? '') . ",
                " . db_escape($data['contact_id'] ?? null) . ",
                " . db_escape($data['communication_type']) . ",
                " . db_escape($data['direction'] ?? 'outbound') . ",
                " . db_escape($data['subject'] ?? '') . ",
                " . db_escape($data['message'] ?? '') . ",
                " . db_escape($data['follow_up_required'] ? 1 : 0) . ",
                " . db_escape($data['follow_up_date'] ?? null) . ",
                " . db_escape($data['notes'] ?? '') . ",
                " . db_escape($user_id) . ",
                NOW()
            )";
    
    db_query($sql, "Could not insert communication");
    return db_insert_id();
}

/**
 * Display CRM dashboard stats
 */
function display_crm_dashboard_stats($stats) {
    global $path_to_root;
    
    start_table(TABLESTYLE, "width='80%'");
    
    echo '<tr>';
    echo '<td align="center" class="tableheader">' . _("Total Customers") . '<br><h1>' . $stats['total_customers'] . '</h1></td>';
    echo '<td align="center" class="tableheader">' . _("Active Opportunities") . '<br><h1>' . $stats['active_opportunities'] . '</h1></td>';
    echo '<td align="center" class="tableheader">' . _("Pending Follow-ups") . '<br><h1>' . $stats['pending_followups'] . '</h1></td>';
    echo '<td align="center" class="tableheader">' . _("Today's Communications") . '<br><h1>' . $stats['communications_today'] . '</h1></td>';
    echo '</tr>';
    
    end_table();
}

/**
 * Display recent CRM activities
 */
function display_crm_recent_activities() {
    $sql = "SELECT al.*, d.name as customer_name
            FROM " . TB_PREF . "fa_crm_activity_log al
            LEFT JOIN " . TB_PREF . "debtors_master d ON al.debtor_no = d.debtor_no
            ORDER BY al.created_at DESC LIMIT 10";
    
    $result = db_query($sql, "Could not get activities");
    
    echo '<h3>' . _("Recent Activities") . '</h3>';
    start_table(TABLESTYLE);
    
    echo '<tr>';
    echo '<th>' . _("Date") . '</th>';
    echo '<th>' . _("Customer") . '</th>';
    echo '<th>' . _("Action") . '</th>';
    echo '<th>' . _("User") . '</th>';
    echo '</tr>';
    
    while ($row = db_fetch_assoc($result)) {
        echo '<tr>';
        echo '<td>' . $row['created_at'] . '</td>';
        echo '<td>' . $row['customer_name'] . '</td>';
        echo '<td>' . $row['action'] . '</td>';
        echo '<td>' . $row['user_id'] . '</td>';
        echo '</tr>';
    }
    
    end_table();
}

/**
 * Get stage CSS class
 */
function get_stage_class($stage) {
    $classes = array(
        'qualification' => 'status_qualification',
        'discovery' => 'status_discovery',
        'proposal' => 'status_proposal',
        'negotiation' => 'status_negotiation',
        'closed_won' => 'status_won',
        'closed_lost' => 'status_lost',
    );
    return $classes[$stage] ?? '';
}

// ============================================================================
// CRM LEADS FUNCTIONS
// ============================================================================

function add_lead($lead_data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_leads
        (debtor_no, lead_source, lead_status, rating, annual_revenue, employee_count,
        industry, website, phone, email, address, assigned_to, campaign_id, notes)
    VALUES (" . db_escape($lead_data['debtor_no']) . ",
            " . db_escape($lead_data['lead_source']) . ",
            " . db_escape($lead_data['lead_status']) . ",
            " . db_escape($lead_data['rating']) . ",
            " . db_escape($lead_data['annual_revenue']) . ",
            " . db_escape($lead_data['employee_count']) . ",
            " . db_escape($lead_data['industry']) . ",
            " . db_escape($lead_data['website']) . ",
            " . db_escape($lead_data['phone']) . ",
            " . db_escape($lead_data['email']) . ",
            " . db_escape($lead_data['address']) . ",
            " . db_escape($lead_data['assigned_to']) . ",
            " . db_escape($lead_data['campaign_id']) . ",
            " . db_escape($lead_data['notes']) . ")";

    db_query($sql, "Could not add lead");
    $lead_id = db_insert_id();
    
    // Dispatch events
    crm_dispatch_event('crm.lead.created', [
        'lead_id' => $lead_id,
        'debtor_no' => $lead_data['debtor_no'] ?? null,
        'data' => $lead_data,
    ]);
    crm_dispatch_event('db_postinsert', ['table' => 'fa_crm_leads', 'id' => $lead_id]);
    
    return $lead_id;
}

function get_lead($lead_id)
{
    $sql = "SELECT l.*, d.name as debtor_name, d.curr_code
        FROM " . TB_PREF . "fa_crm_leads l
        LEFT JOIN " . TB_PREF . "debtors_master d ON l.debtor_no = d.debtor_no
        WHERE l.id = " . db_escape($lead_id);
    $result = db_query($sql, "Could not get lead");
    return db_fetch_assoc($result);
}

function get_lead_by_debtor($debtor_no)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_leads 
        WHERE debtor_no = " . db_escape($debtor_no) . "
        AND converted_to_debtor_no IS NULL";
    $result = db_query($sql, "Could not get lead");
    return db_fetch_assoc($result);
}

function update_lead($lead_id, $lead_data)
{
    $sql = "UPDATE " . TB_PREF . "fa_crm_leads SET
        lead_source = " . db_escape($lead_data['lead_source']) . ",
        lead_status = " . db_escape($lead_data['lead_status']) . ",
        rating = " . db_escape($lead_data['rating']) . ",
        annual_revenue = " . db_escape($lead_data['annual_revenue']) . ",
        employee_count = " . db_escape($lead_data['employee_count']) . ",
        industry = " . db_escape($lead_data['industry']) . ",
        website = " . db_escape($lead_data['website']) . ",
        phone = " . db_escape($lead_data['phone']) . ",
        email = " . db_escape($lead_data['email']) . ",
        address = " . db_escape($lead_data['address']) . ",
        assigned_to = " . db_escape($lead_data['assigned_to']) . ",
        campaign_id = " . db_escape($lead_data['campaign_id']) . ",
        notes = " . db_escape($lead_data['notes']) . "
    WHERE id = " . db_escape($lead_id);

    db_query($sql, "Could not update lead");
    
    // Dispatch events
    crm_dispatch_event('crm.lead.updated', [
        'lead_id' => $lead_id,
        'data' => $lead_data,
    ]);
    crm_dispatch_event('db_postupdate', ['table' => 'fa_crm_leads', 'id' => $lead_id]);
}

function delete_lead($lead_id)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_leads WHERE id = " . db_escape($lead_id);
    $result = db_query($sql);
    $old_data = db_fetch_assoc($result);
    
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_leads WHERE id = " . db_escape($lead_id);
    db_query($sql, "Could not delete lead");
    
    // Dispatch events
    crm_dispatch_event('crm.lead.deleted', [
        'lead_id' => $lead_id,
        'old_data' => $old_data,
    ]);
    crm_dispatch_event('db_postdelete', ['table' => 'fa_crm_leads', 'id' => $lead_id]);
}

function convert_lead($lead_id, $converted_debtor_no)
{
    $lead = get_lead($lead_id);
    if (!$lead) return false;

    // Update lead record
    $sql = "UPDATE " . TB_PREF . "fa_crm_leads SET
        lead_status = 'converted',
        converted_date = NOW(),
        converted_to_debtor_no = " . db_escape($converted_debtor_no) . "
    WHERE id = " . db_escape($lead_id);
    db_query($sql, "Could not convert lead");

    // Insert contact-account relationship if contact exists
    if (!empty($lead['debtor_no'])) {
        $sql = "INSERT IGNORE INTO " . TB_PREF . "fa_crm_contact_accounts
            (contact_id, debtor_no, is_primary, role)
        SELECT c.id, " . db_escape($converted_debtor_no) . ", 1, 'Converted Lead'
        FROM " . TB_PREF . "fa_crm_contacts c
        WHERE c.debtor_no = " . db_escape($lead['debtor_no']) . "
        LIMIT 1";
        db_query($sql, "Could not create contact account link");
    }

    return true;
}

function get_all_leads($status = null, $assigned_to = null)
{
    $sql = "SELECT l.*, d.name as debtor_name
        FROM " . TB_PREF . "fa_crm_leads l
        LEFT JOIN " . TB_PREF . "debtors_master d ON l.debtor_no = d.debtor_no
        WHERE 1=1";

    if ($status) {
        $sql .= " AND l.lead_status = " . db_escape($status);
    }
    if ($assigned_to) {
        $sql .= " AND l.assigned_to = " . db_escape($assigned_to);
    }

    $sql .= " ORDER BY l.created_at DESC";

    $result = db_query($sql, "Could not get leads");
    $leads = array();
    while ($row = db_fetch_assoc($result)) {
        $leads[] = $row;
    }
    return $leads;
}

// ============================================================================
// CRM CONTACT-ACCOUNT JUNCTION FUNCTIONS
// ============================================================================

function add_contact_account($contact_id, $debtor_no, $is_primary = 0, $role = null)
{
    $sql = "INSERT IGNORE INTO " . TB_PREF . "fa_crm_contact_accounts
        (contact_id, debtor_no, is_primary, role)
    VALUES (" . db_escape($contact_id) . ", " . db_escape($debtor_no) . ",
            " . (int)$is_primary . ", " . db_escape($role) . ")";
    db_query($sql, "Could not add contact-account relationship");
}

function remove_contact_account($contact_id, $debtor_no)
{
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_contact_accounts
        WHERE contact_id = " . db_escape($contact_id) . "
        AND debtor_no = " . db_escape($debtor_no);
    db_query($sql, "Could not remove contact-account relationship");
}

function get_contact_accounts($contact_id)
{
    $sql = "SELECT ca.*, d.name as debtor_name
        FROM " . TB_PREF . "fa_crm_contact_accounts ca
        LEFT JOIN " . TB_PREF . "debtors_master d ON ca.debtor_no = d.debtor_no
        WHERE ca.contact_id = " . db_escape($contact_id) . "
        ORDER BY ca.is_primary DESC";
    $result = db_query($sql, "Could not get contact accounts");
    $accounts = array();
    while ($row = db_fetch_assoc($result)) {
        $accounts[] = $row;
    }
    return $accounts;
}

function get_account_contacts($debtor_no)
{
    $sql = "SELECT c.*, ca.is_primary, ca.role
        FROM " . TB_PREF . "fa_crm_contacts c
        LEFT JOIN " . TB_PREF . "fa_crm_contact_accounts ca ON c.id = ca.contact_id
        WHERE ca.debtor_no = " . db_escape($debtor_no) . "
        OR c.debtor_no = " . db_escape($debtor_no) . "
        ORDER BY ca.is_primary DESC, c.is_primary DESC";
    $result = db_query($sql, "Could not get account contacts");
    $contacts = array();
    while ($row = db_fetch_assoc($result)) {
        $contacts[] = $row;
    }
    return $contacts;
}

// ============================================================================
// CRM REALMS FUNCTIONS
// ============================================================================

function add_realm($realm_data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_realms
        (name, description, requires_quote, requires_project, default_stage, stages_json, sort_order)
    VALUES (" . db_escape($realm_data['name']) . ",
            " . db_escape($realm_data['description']) . ",
            " . (int)$realm_data['requires_quote'] . ",
            " . (int)$realm_data['requires_project'] . ",
            " . db_escape($realm_data['default_stage']) . ",
            " . db_escape($realm_data['stages_json']) . ",
            " . (int)$realm_data['sort_order'] . ")";
    db_query($sql, "Could not add realm");
    return db_insert_id();
}

function get_realm($realm_id)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_realms WHERE id = " . db_escape($realm_id);
    $result = db_query($sql, "Could not get realm");
    return db_fetch_assoc($result);
}

function get_all_realms($active_only = true)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_realms";
    if ($active_only) {
        $sql .= " WHERE inactive = 0";
    }
    $sql .= " ORDER BY sort_order, name";
    $result = db_query($sql, "Could not get realms");
    $realms = array();
    while ($row = db_fetch_assoc($result)) {
        $realms[] = $row;
    }
    return $realms;
}

function update_realm($realm_id, $realm_data)
{
    $sql = "UPDATE " . TB_PREF . "fa_crm_realms SET
        name = " . db_escape($realm_data['name']) . ",
        description = " . db_escape($realm_data['description']) . ",
        requires_quote = " . (int)$realm_data['requires_quote'] . ",
        requires_project = " . (int)$realm_data['requires_project'] . ",
        default_stage = " . db_escape($realm_data['default_stage']) . ",
        stages_json = " . db_escape($realm_data['stages_json']) . ",
        sort_order = " . (int)$realm_data['sort_order'] . "
    WHERE id = " . db_escape($realm_id);
    db_query($sql, "Could not update realm");
}

function delete_realm($realm_id)
{
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_realms WHERE id = " . db_escape($realm_id);
    db_query($sql, "Could not delete realm");
}

// ============================================================================
// CRM QUOTES FUNCTIONS
// ============================================================================

function add_quote($quote_data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_quotes
        (quote_no, opportunity_id, debtor_no, contact_id, quote_date, valid_until, status,
        subtotal, tax_rate, tax_amount, total, notes, terms, created_by)
    VALUES (" . db_escape($quote_data['quote_no']) . ",
            " . db_escape($quote_data['opportunity_id']) . ",
            " . db_escape($quote_data['debtor_no']) . ",
            " . db_escape($quote_data['contact_id']) . ",
            " . db_escape($quote_data['quote_date']) . ",
            " . db_escape($quote_data['valid_until']) . ",
            " . db_escape($quote_data['status']) . ",
            " . db_escape($quote_data['subtotal']) . ",
            " . db_escape($quote_data['tax_rate']) . ",
            " . db_escape($quote_data['tax_amount']) . ",
            " . db_escape($quote_data['total']) . ",
            " . db_escape($quote_data['notes']) . ",
            " . db_escape($quote_data['terms']) . ",
            " . db_escape($quote_data['created_by']) . ")";
    db_query($sql, "Could not add quote");
    return db_insert_id();
}

function get_quote($quote_id)
{
    $sql = "SELECT q.*, d.name as customer_name
        FROM " . TB_PREF . "fa_crm_quotes q
        LEFT JOIN " . TB_PREF . "debtors_master d ON q.debtor_no = d.debtor_no
        WHERE q.id = " . db_escape($quote_id);
    $result = db_query($sql, "Could not get quote");
    return db_fetch_assoc($result);
}

function get_all_quotes($opportunity_id = null, $status = null)
{
    $sql = "SELECT q.*, d.name as customer_name, o.opportunity_name
        FROM " . TB_PREF . "fa_crm_quotes q
        LEFT JOIN " . TB_PREF . "debtors_master d ON q.debtor_no = d.debtor_no
        LEFT JOIN " . TB_PREF . "fa_crm_opportunities o ON q.opportunity_id = o.id
        WHERE 1=1";

    if ($opportunity_id) {
        $sql .= " AND q.opportunity_id = " . db_escape($opportunity_id);
    }
    if ($status) {
        $sql .= " AND q.status = " . db_escape($status);
    }

    $sql .= " ORDER BY q.created_at DESC";
    $result = db_query($sql, "Could not get quotes");
    $quotes = array();
    while ($row = db_fetch_assoc($result)) {
        $quotes[] = $row;
    }
    return $quotes;
}

function update_quote($quote_id, $quote_data)
{
    $sql = "UPDATE " . TB_PREF . "fa_crm_quotes SET
        opportunity_id = " . db_escape($quote_data['opportunity_id']) . ",
        debtor_no = " . db_escape($quote_data['debtor_no']) . ",
        contact_id = " . db_escape($quote_data['contact_id']) . ",
        quote_date = " . db_escape($quote_data['quote_date']) . ",
        valid_until = " . db_escape($quote_data['valid_until']) . ",
        status = " . db_escape($quote_data['status']) . ",
        subtotal = " . db_escape($quote_data['subtotal']) . ",
        tax_rate = " . db_escape($quote_data['tax_rate']) . ",
        tax_amount = " . db_escape($quote_data['tax_amount']) . ",
        total = " . db_escape($quote_data['total']) . ",
        notes = " . db_escape($quote_data['notes']) . ",
        terms = " . db_escape($quote_data['terms']) . "
    WHERE id = " . db_escape($quote_id);
    db_query($sql, "Could not update quote");
}

function delete_quote($quote_id)
{
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_quote_items WHERE quote_id = " . db_escape($quote_id);
    db_query($sql, "Could not delete quote items");
    $sql = "DELETE FROM " . TB_PREF . "fa_crm_quotes WHERE id = " . db_escape($quote_id);
    db_query($sql, "Could not delete quote");
}

function add_quote_item($quote_id, $item_data)
{
    $sql = "INSERT INTO " . TB_PREF . "fa_crm_quote_items
        (quote_id, line_number, item_description, quantity, unit_price, unit, discount_percent, discount_amount, line_total)
    VALUES (" . db_escape($quote_id) . ",
            " . (int)$item_data['line_number'] . ",
            " . db_escape($item_data['item_description']) . ",
            " . db_escape($item_data['quantity']) . ",
            " . db_escape($item_data['unit_price']) . ",
            " . db_escape($item_data['unit']) . ",
            " . db_escape($item_data['discount_percent']) . ",
            " . db_escape($item_data['discount_amount']) . ",
            " . db_escape($item_data['line_total']) . ")";
    db_query($sql, "Could not add quote item");
    return db_insert_id();
}

function get_quote_items($quote_id)
{
    $sql = "SELECT * FROM " . TB_PREF . "fa_crm_quote_items
        WHERE quote_id = " . db_escape($quote_id) . "
        ORDER BY line_number";
    $result = db_query($sql, "Could not get quote items");
    $items = array();
    while ($row = db_fetch_assoc($result)) {
        $items[] = $row;
    }
    return $items;
}

// ============================================================================
// OPPORTUNITY-TO-PROJECT WORKFLOW
// ============================================================================

function create_project_from_opportunity($opportunity_id, $project_name = null)
{
    $opp = get_crm_opportunity($opportunity_id);
    if (!$opp) return null;

    // Get realm to check if project is required
    $realm = null;
    if (!empty($opp['realm'])) {
        $realms = get_all_realms(false);
        foreach ($realms as $r) {
            if ($r['name'] == $opp['realm']) {
                $realm = $r;
                break;
            }
        }
    }

    // Check if project required by realm
    if ($realm && !$realm['requires_project']) {
        return null;
    }

    // Create project in FA_PM
    if (empty($project_name)) {
        $project_name = $opp['opportunity_name'] . ' Project';
    }

    // Insert into fa_pm_projects if the table exists
    $sql = "INSERT INTO " . TB_PREF . "fa_pm_projects
        (project_id, name, description, customer_id, status, start_date, lead_source)
    VALUES (
        " . db_escape('PRJ-' . $opportunity_id) . ",
        " . db_escape($project_name) . ",
        " . db_escape($opp['notes']) . ",
        " . db_escape($opp['debtor_no']) . ",
        'Planning',
        NOW(),
        " . db_escape($opp['source']) . "
    )";
    
    // Try - may not exist if PM module not installed
    @$result = db_query($sql);
    if (!$result) return null;

    $project_id = db_insert_id();

    // Update opportunity with project link
    $sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
        project_id = " . db_escape($project_id) . "
    WHERE id = " . db_escape($opportunity_id);
    db_query($sql, "Could not update opportunity");

    // Create default tasks for timesheet tracking
    if ($project_id) {
        $tasks = array(
            'Project Setup' => 'Initial project setup and planning',
            'Requirements Gathering' => 'Collect and document requirements',
            'Design' => 'Design phase',
            'Development' => 'Development work',
            'Testing' => 'Testing and QA',
            'Deployment' => 'Deployment and handoff',
            'Timesheet Review' => 'Review and approve timesheets',
        );
        
        foreach ($tasks as $task_name => $task_desc) {
            @$this->add_task(array(
                'project_id' => $project_id,
                'task_name' => $task_name,
                'description' => $task_desc,
                'status' => 'Pending',
            ));
        }
    }

    return $project_id;
}

function close_opportunity_won($opportunity_id, $new_stage = 'closed_won')
{
    $opp = get_crm_opportunity($opportunity_id);
    if (!$opp) return false;

    // Update stage to closed_won
    $sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
        stage = " . db_escape($new_stage) . ",
        status = 'won',
        actual_close_date = NOW()
    WHERE id = " . db_escape($opportunity_id);
    db_query($sql, "Could not close opportunity");

    // Get realm to check workflow requirements
    $realm = null;
    if (!empty($opp['realm'])) {
        $realms = get_all_realms(false);
        foreach ($realms as $r) {
            if ($r['name'] == $opp['realm']) {
                $realm = $r;
                break;
            }
        }
    }

    // Create project if required
    if ($realm && $realm['requires_project']) {
        create_project_from_opportunity($opportunity_id);
    }

    return true;
}

function close_opportunity_lost($opportunity_id, $lost_reason)
{
    $sql = "UPDATE " . TB_PREF . "fa_crm_opportunities SET
        stage = 'closed_lost',
        status = 'lost',
        lost_reason = " . db_escape($lost_reason) . ",
        actual_close_date = NOW()
    WHERE id = " . db_escape($opportunity_id);
    db_query($sql, "Could not close opportunity");
    return true;
}
