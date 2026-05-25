<?php

function ksf_fa_crm_import_menu()
{
    add_menu_entry('customer_import', 'Import Customers', '', 'customer_import');
}

function ksf_render_crm_import()
{
    $target_fields = [
        'debtor_no',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'tax_id',
    ];

    $processor = function($row) {
        global $db;
        include_once INCLUDES . '/db.inc';
        
        $debtor_no = $row['debtor_no'] ?? '';
        if (empty($debtor_no)) return false;
        
        $check = db_fetch_assoc(db_query(
            "SELECT debtor_no FROM " . TB_PREF . "debtors_master WHERE debtor_no = " . db_escape($debtor_no)
        ));
        
        if ($check) {
            $sets = [];
            foreach ($row as $f => $v) {
                if ($f !== 'debtor_no') $sets[] = "$f = " . db_escape($v);
            }
            db_query("UPDATE " . TB_PREF . "debtors_master SET " . implode(', ', $sets) . " WHERE debtor_no = " . db_escape($debtor_no));
        } else {
            $cols = implode(', ', array_keys($row));
            $vals = implode(', ', array_map(fn($v) => db_escape($v), array_values($row)));
            db_query("INSERT INTO " . TB_PREF . "debtors_master ($cols) VALUES ($vals)");
        }
        
        return ['debtor_no' => $debtor_no];
    };
    
    return ksf_render_import_page('customer', $target_fields, $processor);
}

add_hook('ksf_fa_crm_install', 'ksf_fa_crm_import_menu');