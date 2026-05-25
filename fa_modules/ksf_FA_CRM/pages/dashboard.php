<?php
/**
 * CRM Dashboard
 *
 * WebERP-style CRM dashboard for FrontAccounting
 */

$page_security = 'SA_CUSTOMER';
$path_to_root = "../../..";

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_ui.inc");

$js = "";
if ($SysPrefs->use_popup_windows)
	$js .= get_js_open_window(900, 500);

page(_($help_context = "CRM Dashboard"), false, false, "", $js);

//--------------------------------------------------------------------------------------------

start_table(TABLESTYLE_NOBORDER);
start_row();
crm_navigation_menu();
end_row();
end_table();

echo '<br>';

// Get CRM section from URL
$crm_section = isset($_GET['crm_section']) ? $_GET['crm_section'] : 'dashboard';

switch ($crm_section) {
	case 'customers':
		display_crm_customers_section();
		break;
	case 'opportunities':
		display_crm_opportunities_section();
		break;
	case 'campaigns':
		display_crm_campaigns_section();
		break;
	case 'reports':
		display_crm_reports_section();
		break;
	case 'settings':
		display_crm_settings_section();
		break;
	case 'dashboard':
	default:
		display_crm_dashboard();
		break;
}

end_page();

//--------------------------------------------------------------------------------------------

function display_crm_dashboard()
{
	echo '<div class="crm-dashboard">';

	// CRM Statistics Overview
	crm_dashboard_widget();

	// Recent Activity
	echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">';

	// Recent Customer Activity
	echo '<div>';
	echo '<h3>' . _("Recent Customer Activity") . '</h3>';

	$sql = "SELECT d.name, d.debtor_no, d.last_contact_date,
		(SELECT SUM(ov_amount + ov_gst + ov_freight + ov_freight_tax - ov_discount - alloc)
		 FROM " . TB_PREF . "debtor_trans
		 WHERE debtor_no = d.debtor_no AND type IN (" . ST_SALESINVOICE . ", " . ST_CUSTCREDIT . ")) as outstanding
	FROM " . TB_PREF . "debtors_master d
	WHERE d.last_contact_date IS NOT NULL AND d.inactive = 0
	ORDER BY d.last_contact_date DESC LIMIT 10";

	$result = db_query($sql);

	if (db_num_rows($result) > 0) {
		start_table(TABLESTYLE);
		table_header(array(_("Customer"), _("Last Contact"), _("Outstanding")));

		while ($row = db_fetch($result)) {
			start_row();
			label_cell('<a href="' . $path_to_root . '/sales/manage/enhanced_customers.php?debtor_no=' . $row['debtor_no'] . '">' . $row['name'] . '</a>');
			label_cell($row['last_contact_date'] ? sql2date($row['last_contact_date']) : _('Never'));
			amount_cell($row['outstanding']);
			end_row();
		}

		end_table();
	} else {
		echo '<p>' . _("No recent customer activity") . '</p>';
	}

	echo '</div>';

	// Upcoming Follow-ups
	echo '<div>';
	echo '<h3>' . _("Upcoming Follow-ups") . '</h3>';

	$sql = "SELECT d.name, d.debtor_no, d.next_followup_date, d.account_manager
	FROM " . TB_PREF . "debtors_master d
	WHERE d.next_followup_date >= CURDATE() AND d.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
	AND d.inactive = 0
	ORDER BY d.next_followup_date ASC LIMIT 10";

	$result = db_query($sql);

	if (db_num_rows($result) > 0) {
		start_table(TABLESTYLE);
		table_header(array(_("Customer"), _("Follow-up Date"), _("Account Manager")));

		while ($row = db_fetch($result)) {
			start_row();
			label_cell('<a href="' . $path_to_root . '/sales/manage/enhanced_customers.php?debtor_no=' . $row['debtor_no'] . '">' . $row['name'] . '</a>');
			label_cell(sql2date($row['next_followup_date']));
			label_cell($row['account_manager']);
			end_row();
		}

		end_table();
	} else {
		echo '<p>' . _("No upcoming follow-ups") . '</p>';
	}

	echo '</div>';

	echo '</div>';

	// Quick Actions
	echo '<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px;">';
	echo '<h3>' . _("Quick Actions") . '</h3>';
	echo '<div style="display: flex; gap: 15px; flex-wrap: wrap;">';

	$quick_actions = array(
		array('url' => $path_to_root . '/sales/manage/enhanced_customers.php', 'label' => _("Add Customer"), 'icon' => 'user-plus'),
		array('url' => $path_to_root . '/modules/CRM/pages/customer_types.php', 'label' => _("Manage Types"), 'icon' => 'tags'),
		array('url' => $path_to_root . '/modules/CRM/pages/territories.php', 'label' => _("Manage Territories"), 'icon' => 'map'),
		array('url' => '#', 'label' => _("Export Data"), 'icon' => 'download', 'onclick' => 'alert("Export functionality coming soon")'),
		array('url' => '#', 'label' => _("Import Data"), 'icon' => 'upload', 'onclick' => 'alert("Import functionality coming soon")')
	);

	foreach ($quick_actions as $action) {
		$onclick = isset($action['onclick']) ? ' onclick="' . $action['onclick'] . '"' : '';
		echo '<a href="' . $action['url'] . '"' . $onclick . ' style="display: inline-block; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">';
		echo '<i class="fas fa-' . $action['icon'] . '" style="margin-right: 5px;"></i>' . $action['label'];
		echo '</a>';
	}

	echo '</div>';
	echo '</div>';

	echo '</div>';
}

function display_crm_customers_section()
{
	echo '<div class="crm-customers-section">';

	// Customer filters
	start_table(TABLESTYLE_NOBORDER);
	start_row();

	customer_types_list_cells(_("Filter by Type:"), 'filter_type', null, _('All Types'), true);
	territories_list_cells(_("Filter by Territory:"), 'filter_territory', null, _('All Territories'), true);
	text_cells(_("Search:"), 'search_text', null, 30, 50);

	submit_cells('filter', _("Filter"), '', '', 'default');
	end_row();
	end_table();

	echo '<br>';

	// Build query with filters
	$sql = "SELECT d.debtor_no, d.name, d.debtor_ref, d.address, d.phone, d.email,
		ct.type_name, t.territory_name, d.account_manager, d.last_contact_date,
		d.credit_rating, d.payment_reliability
	FROM " . TB_PREF . "debtors_master d
	LEFT JOIN " . TB_PREF . "crm_customer_types ct ON d.customer_type_id = ct.id
	LEFT JOIN " . TB_PREF . "crm_territories t ON d.territory_id = t.id
	WHERE d.inactive = 0";

	$filter_type = get_post('filter_type');
	$filter_territory = get_post('filter_territory');
	$search_text = get_post('search_text');

	if ($filter_type) {
		$sql .= " AND d.customer_type_id = " . db_escape($filter_type);
	}

	if ($filter_territory) {
		$sql .= " AND d.territory_id = " . db_escape($filter_territory);
	}

	if ($search_text) {
		$sql .= " AND (d.name LIKE " . db_escape('%' . $search_text . '%') . "
			OR d.debtor_ref LIKE " . db_escape('%' . $search_text . '%') . "
			OR d.address LIKE " . db_escape('%' . $search_text . '%') . ")";
	}

	$sql .= " ORDER BY d.name";

	$result = db_query($sql, "Could not get customers");

	start_table(TABLESTYLE);
	table_header(array(_("Customer"), _("Type"), _("Territory"), _("Account Manager"),
		_("Last Contact"), _("Credit Rating"), _("Actions")));

	while ($customer = db_fetch($result)) {
		start_row();

		// Customer name with link
		label_cell('<a href="' . $path_to_root . '/sales/manage/enhanced_customers.php?debtor_no=' . $customer['debtor_no'] . '">' .
			$customer['name'] . '</a><br><small>' . $customer['debtor_ref'] . '</small>');

		label_cell($customer['type_name'] ?: _('Not Set'));
		label_cell($customer['territory_name'] ?: _('Not Set'));
		label_cell($customer['account_manager'] ?: _('Not Assigned'));

		label_cell($customer['last_contact_date'] ? sql2date($customer['last_contact_date']) : _('Never'));

		// Credit rating with color coding
		$rating_colors = array('excellent' => 'green', 'good' => 'blue', 'fair' => 'orange', 'poor' => 'red');
		$color = isset($rating_colors[$customer['credit_rating']]) ? $rating_colors[$customer['credit_rating']] : 'gray';
		label_cell('<span style="color: ' . $color . '; font-weight: bold;">' . ucfirst($customer['credit_rating'] ?: 'unknown') . '</span>');

		// Action buttons
		$actions = '<a href="' . $path_to_root . '/sales/manage/enhanced_customers.php?debtor_no=' . $customer['debtor_no'] . '">' . _("Edit") . '</a> | ';
		$actions .= '<a href="#" onclick="crmQuickAction(\'contact\', \'' . $customer['debtor_no'] . '\')">' . _("Contact") . '</a>';
		label_cell($actions);

		end_row();
	}

	end_table();

	echo '</div>';
}

function display_crm_opportunities_section()
{
	echo '<div class="crm-opportunities-section">';
	echo '<h3>' . _("Sales Opportunities") . '</h3>';

	// Opportunities table
	$sql = "SELECT o.*, d.name as customer_name, t.territory_name
	FROM " . TB_PREF . "crm_opportunities o
	LEFT JOIN " . TB_PREF . "debtors_master d ON o.customer_id = d.debtor_no
	LEFT JOIN " . TB_PREF . "crm_territories t ON d.territory_id = t.id
	ORDER BY o.expected_close_date ASC";

	$result = db_query($sql);

	if (db_num_rows($result) > 0) {
		start_table(TABLESTYLE);
		table_header(array(_("Customer"), _("Opportunity"), _("Value"), _("Probability"),
			_("Expected Close"), _("Status"), _("Sales Person")));

		while ($opp = db_fetch($result)) {
			start_row();
			label_cell($opp['customer_name']);
			label_cell($opp['opportunity_name']);
			amount_cell($opp['estimated_value']);
			label_cell($opp['probability'] . '%');
			label_cell(sql2date($opp['expected_close_date']));
			label_cell($opp['status']);
			label_cell($opp['sales_person']);
			end_row();
		}

		end_table();
	} else {
		echo '<p>' . _("No sales opportunities found") . '</p>';
	}

	echo '<div style="margin-top: 20px;">';
	echo '<a href="#" onclick="window.open(\'' . $path_to_root . '/modules/CRM/pages/add_opportunity.php\', \'_blank\', \'width=700,height=500\')" style="padding: 10px 15px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">';
	echo '<i class="fas fa-plus"></i> ' . _("Add New Opportunity");
	echo '</a>';
	echo '</div>';

	echo '</div>';
}

function display_crm_campaigns_section()
{
	echo '<div class="crm-campaigns-section">';
	echo '<h3>' . _("Marketing Campaigns") . '</h3>';
	echo '<p>' . _("Campaign management functionality coming soon.") . '</p>';
	echo '</div>';
}

function display_crm_reports_section()
{
	echo '<div class="crm-reports-section">';
	echo '<h3>' . _("CRM Reports") . '</h3>';

	$reports = array(
		array('name' => 'Customer Summary', 'description' => 'Overview of all customers with key metrics'),
		array('name' => 'Sales Pipeline', 'description' => 'Current sales opportunities and pipeline value'),
		array('name' => 'Customer Segmentation', 'description' => 'Customers grouped by type and segment'),
		array('name' => 'Territory Performance', 'description' => 'Sales performance by territory'),
		array('name' => 'Customer Lifetime Value', 'description' => 'Analysis of customer profitability over time')
	);

	start_table(TABLESTYLE);
	table_header(array(_("Report Name"), _("Description"), _("Actions")));

	foreach ($reports as $report) {
		start_row();
		label_cell('<strong>' . $report['name'] . '</strong>');
		label_cell($report['description']);
		label_cell('<button onclick="alert(\'Report generation coming soon\')" style="padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer;">' . _("Generate") . '</button>');
		end_row();
	}

	end_table();

	echo '</div>';
}

function display_crm_settings_section()
{
	echo '<div class="crm-settings-section">';
	echo '<h3>' . _("CRM Settings") . '</h3>';

	echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">';

	// Quick links to management pages
	echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">';
	echo '<h4>' . _("Customer Management") . '</h4>';
	echo '<ul>';
	echo '<li><a href="' . $path_to_root . '/modules/CRM/pages/customer_types.php">' . _("Customer Types") . '</a></li>';
	echo '<li><a href="' . $path_to_root . '/modules/CRM/pages/territories.php">' . _("Sales Territories") . '</a></li>';
	echo '<li><a href="' . $path_to_root . '/sales/manage/enhanced_customers.php">' . _("Enhanced Customer Management") . '</a></li>';
	echo '</ul>';
	echo '</div>';

	echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">';
	echo '<h4>' . _("System Configuration") . '</h4>';
	echo '<ul>';
	echo '<li>' . _("Default customer settings") . '</li>';
	echo '<li>' . _("CRM workflow configuration") . '</li>';
	echo '<li>' . _("Email integration settings") . '</li>';
	echo '<li>' . _("EDI configuration") . '</li>';
	echo '</ul>';
	echo '</div>';

	echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 5px;">';
	echo '<h4>' . _("Data Management") . '</h4>';
	echo '<ul>';
	echo '<li>' . _("Import customer data") . '</li>';
	echo '<li>' . _("Export CRM data") . '</li>';
	echo '<li>' . _("Data backup and restore") . '</li>';
	echo '<li>' . _("Analytics recalculation") . '</li>';
	echo '</ul>';
	echo '</div>';

	echo '</div>';

	echo '</div>';
}