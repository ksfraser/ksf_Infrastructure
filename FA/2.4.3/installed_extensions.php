<?php

/* List of installed additional extensions. If extensions are added to the list manually
	make sure they have unique and so far never used extension_ids as a keys,
	and $next_extension_id is also updated. More about format of this file yo will find in 
	FA extension system documentation.
*/

$next_extension_id = 2; // unique id for next installed extension

$installed_extensions = array (
  '' => 
  array (
    'package' => 'ksf_FA_API',
    'name' => 'ksf_FA_API',
    'version' => '-',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_API',
    'active' => false,
  ),
  1 => 
  array (
    'package' => 'ksf_FA_Calendar',
    'name' => 'ksf_FA_Calendar',
    'version' => '-',
    'available' => '',
    'type' => 'extension',
    'path' => 'modules/ksf_FA_Calendar',
    'active' => false,
  ),
);
