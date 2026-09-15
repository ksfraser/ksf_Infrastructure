<?php

/* List of installed additional extensions. If extensions are added to the list manually
	make sure they have unique and so far never used extension_ids as a keys,
	and $next_extension_id is also updated. More about format of this file yo will find in 
	FA extension system documentation.
*/

$next_extension_id = 7; // unique id for next installed extension

$installed_extensions = array (
  '' => 
  array (
    'package' => 'ksf_FA_ImportStagingProcessing',
    'name' => 'ksf_FA_ImportStagingProcessing',
    'version' => '2.4.4',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_ImportStagingProcessing',
    'active' => false,
  ),
  1 => 
  array (
    'package' => 'FA_ProductAttributes',
    'name' => 'FA_ProductAttributes',
    'version' => '2.4.4',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/FA_ProductAttributes',
    'active' => true,
  ),
  '2' => 
  array (
    'package' => 'ksf_FA_Woocommerce',
    'name' => 'ksf_FA_Woocommerce',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Woocommerce',
    'active' => false,
  ),
  3 => 
  array (
    'package' => 'ksf_Calendar',
    'name' => 'ksf_Calendar',
    'version' => '2.4.4',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_Calendar',
    'active' => false,
  ),
  4 => 
  array (
    'package' => 'ksf_FA_CRM',
    'name' => 'ksf_FA_CRM',
    'version' => '2.4.3-0',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_CRM',
    'active' => false,
  ),
  5 => 
  array (
    'package' => 'ksf_FA_DataIntegrity',
    'name' => 'ksf_FA_DataIntegrity',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_DataIntegrity',
    'active' => false,
  ),
  6 => 
  array (
    'package' => 'ksf_FA_HRM',
    'name' => 'ksf_FA_HRM',
    'version' => '2.4.3-1',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_HRM',
    'active' => false,
  ),
);
