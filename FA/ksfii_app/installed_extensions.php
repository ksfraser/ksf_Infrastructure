<?php

/* List of installed additional extensions. If extensions are added to the list manually
	make sure they have unique and so far never used extension_ids as a keys,
	and $next_extension_id is also updated. More about format of this file yo will find in 
	FA extension system documentation.
*/

$next_extension_id = 37; // unique id for next installed extension

$installed_extensions = array (
  1 => 
  array (
    'package' => 'ksf_FA_CRM',
    'name' => 'ksf_FA_CRM',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_CRM',
    'active' => false,
  ),
  2 => 
  array (
    'package' => 'ksf_FA_Calendar',
    'name' => 'ksf_FA_Calendar',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Calendar',
    'active' => false,
  ),
  3 => 
  array (
    'package' => 'ksf_FA_Contacts',
    'name' => 'ksf_FA_Contacts',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Contacts',
    'active' => false,
  ),
  4 => 
  array (
    'package' => 'ksf_FA_Customer',
    'name' => 'ksf_FA_Customer',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Customer',
    'active' => false,
  ),
  5 => 
  array (
    'package' => 'ksf_FA_Coupons',
    'name' => 'ksf_FA_Coupons',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Coupons',
    'active' => false,
  ),
  6 => 
  array (
    'package' => 'ksf_FA_DataIntegrity',
    'name' => 'ksf_FA_DataIntegrity',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_DataIntegrity',
    'active' => false,
  ),
  7 => 
  array (
    'package' => 'ksf_FA_DynamicPricing',
    'name' => 'ksf_FA_DynamicPricing',
    'version' => '2.4.3',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_DynamicPricing',
    'active' => false,
  ),
  8 => 
  array (
    'package' => 'ksf_FA_EmailManager',
    'name' => 'ksf_FA_EmailManager',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_EmailManager',
    'active' => false,
  ),
  9 => 
  array (
    'package' => 'ksf_FA_Employee',
    'name' => 'ksf_FA_Employee',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Employee',
    'active' => false,
  ),
  10 => 
  array (
    'package' => 'ksf_FA_ExpenseReport',
    'name' => 'ksf_FA_ExpenseReport',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ExpenseReport',
    'active' => false,
  ),
  11 => 
  array (
    'package' => 'ksf_FA_GPG',
    'name' => 'ksf_FA_GPG',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_GPG',
    'active' => false,
  ),
  12 => 
  array (
    'package' => 'ksf_FA_HRM',
    'name' => 'ksf_FA_HRM',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_HRM',
    'active' => false,
  ),
  13 => 
  array (
    'package' => 'ksf_FA_ImportStagingProcessing',
    'name' => 'ksf_FA_ImportStagingProcessing',
    'version' => '2.4.4',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ImportStagingProcessing',
    'active' => false,
  ),
  14 => 
  array (
    'package' => 'ksf_FA_InvoiceAllocation',
    'name' => 'ksf_FA_InvoiceAllocation',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_InvoiceAllocation',
    'active' => false,
  ),
  15 => 
  array (
    'package' => 'ksf_FA_Logging',
    'name' => 'ksf_FA_Logging',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Logging',
    'active' => false,
  ),
  16 => 
  array (
    'package' => 'ksf_FA_Mail',
    'name' => 'ksf_FA_Mail',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Mail',
    'active' => false,
  ),
  17 => 
  array (
    'package' => 'ksf_FA_ManageGL',
    'name' => 'ksf_FA_ManageGL',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ManageGL',
    'active' => false,
  ),
  18 => 
  array (
    'package' => 'ksf_FA_ManufacturerConsolidation',
    'name' => 'ksf_FA_ManufacturerConsolidation',
    'version' => '2.4.19-1.0.0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ManufacturerConsolidation',
    'active' => false,
  ),
  19 => 
  array (
    'package' => 'ksf_FA_Notes',
    'name' => 'ksf_FA_Notes',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Notes',
    'active' => false,
  ),
  20 => 
  array (
    'package' => 'ksf_FA_ProjectManagement',
    'name' => 'ksf_FA_ProjectManagement',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ProjectManagement',
    'active' => false,
  ),
  21 => 
  array (
    'package' => 'ksf_FA_PurchaseOrderTracking',
    'name' => 'ksf_FA_PurchaseOrderTracking',
    'version' => '2.4.19-1.0.0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_PurchaseOrderTracking',
    'active' => false,
  ),
  22 => 
  array (
    'package' => 'ksf_FA_QuickBudget',
    'name' => 'ksf_FA_QuickBudget',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_QuickBudget',
    'active' => false,
  ),
  23 => 
  array (
    'package' => 'ksf_FA_RBAC',
    'name' => 'ksf_FA_RBAC',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_RBAC',
    'active' => false,
  ),
  24 => 
  array (
    'package' => 'ksf_FA_RelatedTo',
    'name' => 'ksf_FA_RelatedTo',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_RelatedTo',
    'active' => false,
  ),
  25 => 
  array (
    'package' => 'ksf_FA_Rep_SalesGroups',
    'name' => 'ksf_FA_Rep_SalesGroups',
    'version' => '2.4.19',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Rep_SalesGroups',
    'active' => false,
  ),
  26 => 
  array (
    'package' => 'ksf_FA_SerialNumber',
    'name' => 'ksf_FA_SerialNumber',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_SerialNumber',
    'active' => false,
  ),
  27 => 
  array (
    'package' => 'ksf_FA_Shipping',
    'name' => 'ksf_FA_Shipping',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Shipping',
    'active' => false,
  ),
  28 => 
  array (
    'package' => 'ksf_FA_Square',
    'name' => 'ksf_FA_Square',
    'version' => '2.4.4',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Square',
    'active' => false,
  ),
  29 => 
  array (
    'package' => 'ksf_FA_StockTurnover',
    'name' => 'ksf_FA_StockTurnover',
    'version' => '2.4.19-1.0.0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_StockTurnover',
    'active' => false,
  ),
  30 => 
  array (
    'package' => 'ksf_FA_Upc2Item',
    'name' => 'ksf_FA_Upc2Item',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Upc2Item',
    'active' => false,
  ),
  31 => 
  array (
    'package' => 'ksf_FA_Users',
    'name' => 'ksf_FA_Users',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Users',
    'active' => false,
  ),
  32 => 
  array (
    'package' => 'ksf_FA_Wallet',
    'name' => 'ksf_FA_Wallet',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Wallet',
    'active' => false,
  ),
  33 => 
  array (
    'package' => 'ksf_FA_Warehouse',
    'name' => 'ksf_FA_Warehouse',
    'version' => '2.4.19-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Warehouse',
    'active' => false,
  ),
  34 => 
  array (
    'package' => 'ksf_FA_Woocommerce',
    'name' => 'ksf_FA_Woocommerce',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Woocommerce',
    'active' => false,
  ),
  35 => 
  array (
    'package' => 'ksf_FA_Workflow',
    'name' => 'ksf_FA_Workflow',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Workflow',
    'active' => false,
  ),
  36 => 
  array (
    'package' => 'ksf_fa_downloader',
    'name' => 'ksf_fa_downloader',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_fa_downloader',
    'active' => false,
  ),
);
