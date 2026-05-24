# AGENTS.md - ksf_Infrastructure

## Architecture Overview

**Infrastructure** repository containing init SQL, Docker/Podman configs, Ansible playbooks, and deployment clones for the FA ecosystem.

### Core Principles
- **IaC**: Infrastructure as Code
- **DRY**: Don't Repeat Yourself
- **Versioned**: All infrastructure is version-controlled

## Repository Structure

```
ksf_Infrastructure/
├── fa_modules/             # Deployment clones — git pull only, never dev here
│   ├── ksf_FA_Calendar/
│   ├── ksf_Calendar/
│   └── ...
├── podman/                 # Podman Compose configs
│   ├── ksf-compose.yaml
│   ├── .env                # credentials (gitignored)
│   └── .env.example
├── ansible/                # Ansible provisioning
│   ├── ksf-playbook.yaml
│   └── inventories/
├── docker/fa-alpine/       # FA Docker image + reference FA source files
│   ├── Dockerfile
│   ├── docker-compose.yml
│   └── fa_files/includes/  # FA include reference (session, db, access)
└── ProjectDocs/
    ├── Requirements.md
    └── Architecture.md
```

## Container Topology

- Runtime: **Podman** (`/usr/bin/podman`) — not Docker
- `ksf-fa` — Apache + PHP 7.4; FA root at `/var/www/html/`
- `ksf-mariadb` — MariaDB
- `ksf-wp` — WordPress
- FA modules volume: Podman named volume `fa_modules` → `/var/www/html/modules`
  - Updated by running `git pull` in `fa_modules/<module>/` subdirectories
- Apache error.log → `/dev/stderr`; view PHP errors with:
  ```
  podman logs ksf-fa 2>&1 1>/dev/null
  ```

## FA Database Credentials

| Field | Value |
|-------|-------|
| Host | `ksf-mariadb` |
| DB | `ksf_fa` |
| User | `ksf_user` / `ksfuser2024!` |
| Root | `ksfroot2024!` |
| Table prefix | `0_` (constant `TB_PREF`) |

## FA DB Layer — Correct API

FA's `$db` is a raw `mysqli` object. **Do not** call `$db->query($sql, $params)`.

```php
// CORRECT — use FA procedural wrappers
global $db;
$escaped = mysqli_real_escape_string($db, $value);  // escape params manually
$result  = db_query("SELECT * FROM " . TB_PREF . "table WHERE col='" . $escaped . "'");
$row     = db_fetch_assoc($result);   // returns false (not null) when exhausted
$count   = db_num_rows($result);
$id      = db_insert_id();            // handles sql_trail case
$aff     = db_num_affected_rows();

// db_query() substitutes TB_PREF via str_replace — multi-company safe
// db_escape() is NOT safe for SQL params — it HTML-decodes/encodes first
```

## FA Page Security — CRITICAL for Module Pages

FA module pages are standalone PHP files accessed directly as URLs
(e.g. `/modules/ksf_FA_Calendar/cal.php`) — they are NOT included from `index.php`.

`page_header()` calls `check_page_security($page_security)` which looks up
`$security_areas[$page_security]`. Extension module security areas (registered
via `hooks.php`) are only populated by `add_access_extensions()`. Stock FA only
calls this from `index.php` and `admin/security_roles.php`.

**Every module page entry script must call `add_access_extensions()` after
`session.inc` and before `page_header()`:**

```php
$path_to_root = "../..";
include_once($path_to_root . "/includes/session.inc");
add_access_extensions();   // ← required on every direct-access module page
// ... set $page_security, include header.inc, call page_header() ...
```

Without this call, `can_access_page()` returns false for all extension areas
and the user sees a security error (~855 bytes blank page).

## KSF Inter-Module Query Hook System

Module entry-point scripts define constants and config that are unavailable
when hooks.php is loaded (install_hooks() runs early in session.inc, before
any page script is reached). To solve this, KSF modules implement a
standardised hook-based query protocol:

### Defined Hook Names

| Hook Name | FA Function | Direction | Purpose |
|---|---|---|---|
| `ksf_get_value` | `hook_invoke_first` | Consumer → Provider | Query a single named value |
| `ksf_get_values` | `hook_invoke_all` | Consumer → All Providers | Query multiple values |
| `ksf_set_value` | `hook_invoke_all` | Sender → All Modules | Push a value / notify |

### Consumer Pattern (any module page or service)

```php
// Single value — first provider that recognises the key responds
$apiVersion = hook_invoke_first('ksf_get_value', 'calendar.api_version');
if ($apiVersion !== null) {
    // calendar module is installed and responded
}

// Multiple values — all providers respond with their matching keys
$all = hook_invoke_all('ksf_get_values', ['calendar.api_version', 'rbac.hooks_version']);
```

### Provider Pattern (in hooks.php)

```php
function ksf_get_value($key, $opts = array()) {
    $registry = [
        'my_module.version'  => '1.2.0',
        'my_module.api_key'  => defined('MY_API_KEY') ? MY_API_KEY : null,
        'my_module.pref'     => function_exists('get_company_pref')
            ? get_company_pref('my_pref') : null,
    ];
    return array_key_exists($key, $registry) ? $registry[$key] : null;
}
```

### Key Namespacing Convention

Keys MUST be namespaced as `<module>.<value_name>` to prevent collisions
(e.g. `calendar.api_version`, `rbac.hooks_version`).

### Full Template

A ready-to-copy hooks.php template with all patterns is at:
`doc/templates/hooks-template.php`

### Extending for Module-Specific Queries

Beyond the generic `ksf_get_value` pattern, modules may register
domain-specific hook names for richer queries:

```php
// In hooks.php
function calendar_entry_create(&$data, $opts = array()) { ... }
function calendar_entries_query(&$data, $opts = array())  { ... }
```

These follow FA's standard convention: `&$data` passed by reference,
`$opts` for context, return null for "not handled".

### Why Not a Service Locator / DI Container?

- FA has no DI container and adding one is a breaking change.
- `hook_invoke_first` / `hook_invoke_all` are already available and tested.
- The pattern works identically in FA 2.4+ without any core modifications.
- Modules that don't implement a given hook simply don't respond — no crash.

---

## FA install.sql / Schema Convention

- Use `@TB_PREF@` as placeholder in SQL files (e.g. `@TB_PREF@fa_cal_entries`)
- FA's `activate_extension()` → `update_databases()` substitutes automatically
- Manual substitute for testing: `sed 's/@TB_PREF@/0_/g' install.sql | mysql ...`

## Dependencies

- **FrontAccounting 2.4+**
- **MariaDB 10.5+**
- **PHP 7.4** (hard constraint — no PHP 8+ syntax)
