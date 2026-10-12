<?php
/**
 * CLI-ONLY GUARD.
 *
 * This script REWRITES the live extension registry
 * (file_put_contents($path_to_root . "/company/$company/installed_extensions.php")).
 *
 * It must never be reachable over HTTP. It previously lived at
 * modules/modules_dep_needs/e2e_module_dependencies.php, where any crawler,
 * link-checker, browser history entry or security scan that requested it
 * silently rewrote the production registry - and a malformed write emitted an
 * empty-string array key as bare "=> ", which is invalid PHP and 500'd every
 * page in the application.
 *
 * Two defences: this guard, and relocation outside the web root.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit("CLI only.\n");
}
/**
 * e2e for the ModuleDependencies activation gate.
 *
 * Runs the REAL activation code path (activate_hooks -> activate_extension) with the
 * REAL per-company registry, and traps FA's warning instead of letting it render.
 *
 * Why no browser is needed: display_warning() is trigger_error($msg, E_USER_WARNING).
 * FA installs its own handler (`set_error_handler('error_handler')` in
 * includes/session.inc) which appends to `global $messages`. We chain onto it with
 * our own handler that records and returns true, so nothing is ever printed. The
 * $messages global is precisely what the extension page would render, so asserting on
 * it asserts on what the operator would see.
 *
 * @BABOK Related: UT-MODDEP-002
 */

$path_to_root = '/var/www/html';
chdir($path_to_root);

$company = 0;
$regFile = "$path_to_root/company/$company/installed_extensions.php";

$NEEDED = 'modules_dep_needed';
$NEEDS  = 'modules_dep_needs';

$pass = 0;
$fail = 0;

function out($m) { echo $m . "\n"; }
function ok($cond, $label, $detail = '') {
    global $pass, $fail;
    if ($cond) { $pass++; out("    PASS  $label"); }
    else { $fail++; out("    FAIL  $label" . ($detail !== '' ? "\n          $detail" : '')); }
}

/** Chain an error handler that captures E_USER_* without printing. */
function trapWarnings(array &$captured) {
    // Chain: record, then DEFER to FA's handler (return false) so $messages is
    // populated exactly as the real page would. Nothing prints because FA's
    // error_handler() only appends to $messages.
    set_error_handler(function ($errno, $errstr) use (&$captured) {
        if ($errno & (E_USER_WARNING | E_USER_ERROR | E_USER_NOTICE)) {
            $captured[] = $errstr;
        }
        return false;
    });
}
function releaseTrap() { restore_error_handler(); }

/** Reset both test modules to inactive in the per-company registry. */
function setActive(string $regFile, string $module, bool $active, int $nextId) {
    // The registry file ASSIGNS $installed_extensions; it does not merge into a
    // pre-seeded local. Initialise the SAME name or the include() result is
    // discarded and this silently rewrites the registry to zero rows.
    $installed_extensions = array();
    include($regFile);
    $exts = $installed_extensions;
    $found = false;
    foreach ($exts as $k => $ext) {
        if ($ext['package'] === $module) {
            $exts[$k]['active'] = $active ? 1 : false;
            $found = true;
        }
    }
    if (!$found) {
        $exts[$nextId] = [
            'package' => $module, 'name' => $module, 'version' => '2.4.3-1',
            'available' => '', 'type' => 'extension',
            'path' => 'modules/' . $module, 'active' => $active ? 1 : false,
        ];
    }
    $php = "<?php\n\n\$installed_extensions = array (\n";
    foreach ($exts as $k => $ext) {
        $php .= "  $k => \n  array (\n";
        foreach ($ext as $ek => $ev) {
            $php .= "    '$ek' => " . var_export($ev, true) . ",\n";
        }
        $php .= "  ),\n";
    }
    $php .= ");\n";
    file_put_contents($regFile, $php);
}

function isActive(string $regFile, string $module) {
    $installed_extensions = array();
    include($regFile);
    foreach ($installed_extensions as $ext) {
        if ($ext['package'] === $module) {
            return !empty($ext['active']);
        }
    }
    return false;
}

function tableExists(string $tblpref, string $table) {
    $res = db_query("SHOW TABLES LIKE '" . $tblpref . $table . "'");
    return (db_num_rows($res) > 0);
}

// ── Bootstrap just enough FA for the real activation path ────────────────────
require_once "$path_to_root/includes/errors.inc";
set_error_handler('error_handler');
require_once "$path_to_root/includes/hooks.inc";
require_once "$path_to_root/includes/packages.inc";
require_once "$path_to_root/includes/ui/ui_msgs.inc";
require_once "$path_to_root/admin/db/company_db.inc";
require_once "$path_to_root/admin/db/maintenance_db.inc";
require_once "$path_to_root/config_db.php";
require_once "$path_to_root/includes/db/connect_db.inc";   // db_profile() etc
require_once "$path_to_root/includes/db/sql_functions.inc";
require_once "$path_to_root/includes/db/connect_db_mysqli.inc";

// set_global_connection() touches the session user; give it a stub so the real
// activation path (update_databases -> set_global_connection) can run headless.
if (!isset($_SESSION)) { $_SESSION = []; }
if (!isset($_SESSION['wa_current_user'])) { $_SESSION['wa_current_user'] = new stdClass(); }
if (!function_exists('user_company')) {
    function user_company() { return 0; }
}
// set_global_connection() calls $SysPrefs->refresh(); headless, so stub it.
class E2ESysPrefs
{
    public $go_debug = 0;
    public $db_ok = true;
    public $sql_trail = 0;
    public function refresh() { return true; }
}
$SysPrefs = new E2ESysPrefs();

set_global_connection($company);
$tbpref = $db_connections[$company]['tbpref'];

foreach ([$NEEDED, $NEEDS] as $m) {
    $hp = "$path_to_root/modules/$m/hooks.php";
    if (file_exists($hp)) { include_once($hp); }
}

out("FA path: $path_to_root");
out("registry: $regFile");
out("");

// ── Scenario A: _needs first, with _needed inactive ───────────────────────────
out("A. activate _needs while _needed is INACTIVE");
setActive($regFile, $NEEDED, false, 90);
setActive($regFile, $NEEDS,  false, 91);
$Hooks = [];
$GLOBALS['Hooks'] = ['modules_dep_needed' => new hooks_modules_dep_needed()];

$captured = [];
trapWarnings($captured);
$result = activate_hooks($NEEDS, $company, true);
releaseTrap();

$expected = "modules_dep_needs cannot be activated: we need module modules_dep_needed to be activated first.";
ok($result === false, "activate_extension() returned false", 'got ' . var_export($result, true));
ok(in_array($expected, $captured, true), "warning message is exactly the expected text",
   "got: " . var_export($captured, true));
ok(in_array($expected, $messages, true), "message reached global \$messages (what the page renders)");
ok(isActive($regFile, $NEEDS) === false, "registry active flag stayed FALSE");
ok(tableExists($tbpref, 'modules_dep_needs_marker') === false,
   "no schema was created for the refused module");
out("");

// ── Scenario B: _needed active, then _needs ──────────────────────────────────
out("B. activate _needed, then activate _needs");
setActive($regFile, $NEEDED, true, 90);
setActive($regFile, $NEEDS,  false, 91);
$GLOBALS['Hooks'] = [
    'modules_dep_needed' => new hooks_modules_dep_needed(),
    'modules_dep_needs'  => new hooks_modules_dep_needs(),
];

$captured = [];
trapWarnings($captured);
$rNeeded = activate_hooks($NEEDED, $company, true);
$rNeeds  = activate_hooks($NEEDS, $company, true);
releaseTrap();

ok($rNeeded === true, "_needed activated cleanly", 'got ' . var_export($rNeeded, true));
ok($rNeeds === true, "_needs activated cleanly", 'got ' . var_export($rNeeds, true));
ok($captured === [], "no warnings emitted", var_export($captured, true));
ok(isActive($regFile, $NEEDS) === false,
   "registry still false (FA's own write_extensions owns the flag, not our test harness)");
out("");

// ── Scenario C: reverse order, both toggled in ONE POST like the real screen ──
out("C. both ticked in one Refresh/Update POST, _needs sorts first");
setActive($regFile, $NEEDED, false, 90);
setActive($regFile, $NEEDS,  false, 91);
$GLOBALS['Hooks'] = [
    'modules_dep_needed' => new hooks_modules_dep_needed(),
    'modules_dep_needs'  => new hooks_modules_dep_needs(),
];

$captured = [];
trapWarnings($captured);
$overall = true;
$ret = null;
if (true) $ret = activate_hooks($NEEDS, $company, true);
if ($ret !== null) $overall = ($overall && $ret);
$ret = null;
if (true) $ret = activate_hooks($NEEDED, $company, true);
if ($ret !== null) $overall = ($overall && $ret);
releaseTrap();

ok($overall === false, "aggregate \$result is false -> FA prints 'Status change for some extensions failed.'",
   'got ' . var_export($overall, true));
ok(count($captured) === 1, "exactly one warning raised", var_export($captured, true));
ok(isset($captured[0]) && strpos($captured[0], 'we need module modules_dep_needed') !== false,
   "that warning names the missing module", var_export($captured, true));
ok(tableExists($tbpref, 'modules_dep_needed_marker') === true,
   "_needed schema WAS created (it was not blocked)");
out("");

// ── Scenario D: re-activate _needs after _needed is already active ───────────
out("D. re-activate _needs with _needed already active (self-heal)");
setActive($regFile, $NEEDED, true, 90);
setActive($regFile, $NEEDS,  true, 91);
$GLOBALS['Hooks'] = [
    'modules_dep_needed' => new hooks_modules_dep_needed(),
    'modules_dep_needs'  => new hooks_modules_dep_needs(),
];
$captured = [];
trapWarnings($captured);
$r = activate_hooks($NEEDS, $company, true);
releaseTrap();
ok($r === true, "_needs re-activates without warning", 'got ' . var_export($r, true));
out("");

// ── Scenario E: requireHooks catches 'active flag set but hooks never loaded' ──
out("E. registry says active, but \$Hooks has no entry (stale activation)");
setActive($regFile, $NEEDED, true, 90);
setActive($regFile, $NEEDS,  false, 91);
$GLOBALS['Hooks'] = [];   // neither module's hooks.php was included
$captured = [];
trapWarnings($captured);
$r = activate_hooks($NEEDS, $company, true);
releaseTrap();
ok($r === false, "activation refuses a flag-only dependency", 'got ' . var_export($r, true));
ok(!empty($captured) && strpos($captured[0], 'modules_dep_needed') !== false,
   "warning names modules_dep_needed", var_export($captured, true));
out("");

// ── Cleanup ──────────────────────────────────────────────────────────────────
db_query("DROP TABLE IF EXISTS " . $tbpref . "modules_dep_needs_marker");
db_query("DROP TABLE IF EXISTS " . $tbpref . "modules_dep_needed_marker");
setActive($regFile, $NEEDED, false, 90);
setActive($regFile, $NEEDS,  false, 91);
out("cleanup: dropped both marker tables, both registry flags set false");

out("");
out("RESULT: $pass passed, $fail failed");
exit($fail === 0 ? 0 : 1);
