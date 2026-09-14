<?php
/**
 * ksf_FA_ExpenseReport — FrontAccounting Expense Report module.
 *
 * Parent expense-report carrier. Handles multiple expense types (travel, hotel,
 * meals, mileage, per-diem, misc), a report header + line items, and a workflow
 * lane riding submit → approved/denied/returned states. TravelExpense rides as a
 * report type; this module is the 9th lane beside HRM's 8 registerWorkflowType
 * registrations.
 *
 * BABOK traceability:
 *   @BR-007  Expense Report
 *   @FR-007-001 expense-type catalogue (Hotel/Airfare/Fuel/Mileage/Meals/PerDiem/Misc)
 *   @FR-007-002 expense-report header (dates, employee, department, workflow status)
 *   @FR-007-003 expense-report line items (expense_type, date, amount, qty, notes)
 *   @FR-007-004 submit/deny/return workflow states
 *   @FR-007-005 per-expense-type seeds ride the sql/install.sql activated call
 *
 * @package ksfraser\FrontAccounting\ExpenseReport
 */
class hooks_ksf_fa_expensereport extends hooks
{
    use \ksfraser\FrontAccounting\Common\WorkflowHooksTrait;

    public $module_name = 'ksf_FA_ExpenseReport';
    public $version = '1.0.0';

    public function __construct()
    {
        $this->module_name = 'ksf_FA_ExpenseReport';
        $this->version = '1.0.0';
    }

    public function install_tabs($app)
    {
        if ($app->id == 'KSF_EXPENSE') {
            $app->add_rapp_function(1, _('Expense Reports'), 'modules/ksf_FA_ExpenseReport/src/expense_report.php', 'SA_EXPENSEREPORT');
            $app->add_rapp_function(2, _('Expense Report List'), 'modules/ksf_FA_ExpenseReport/src/expense_report_list.php', 'SA_EXPENSEREPORT');
        }
    }

    public function install_access()
    {
        $security = new security();
        $security->add_area('SA_EXPENSEREPORT', _('Expense Report Access'), 'sa_expense_report');
    }

    public function init()
    {
        $this->registerWorkflowType('expense_report', 'exp_expense_report');
        $this->registerWorkflowType('expense', 'exp_expense');
    }
}
