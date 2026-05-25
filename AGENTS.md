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
// Single value — use a variable (FA passes $data by reference)
$key = 'calendar.api_version';
$apiVersion = hook_invoke_first('ksf_get_value', $key);
if ($apiVersion !== null) {
    // calendar module is installed and responded
}

// Multiple values — same rule, no array literals by reference
$queryKeys = ['calendar.api_version', 'rbac.hooks_version'];
$all = hook_invoke_all('ksf_get_values', $queryKeys);
```

### Provider Pattern (in hooks.php)

Use `Ksfraser\Traits\HookQueryProviderTrait` (published in `ksfraser/traits`):

```php
class hooks_ksf_FA_MyModule extends hooks {
    use \Ksfraser\Traits\HookQueryProviderTrait;

    protected function _getAdvertisedValues(): array
    {
        return array(
            'my_module.version'  => '1.2.0',
            'my_module.api_key'  => defined('MY_API_KEY') ? MY_API_KEY : null,
            'my_module.pref'     => function_exists('get_company_pref')
                ? get_company_pref('my_pref') : null,
        );
    }
}
```

The trait provides `ksf_get_value()`, `ksf_get_values()`, and
`ksf_set_value()` — no need to implement them manually.

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

## KSF CRUD Event Hook System

FA's `hook_invoke_all()` enables any module to react when another module
creates, updates, or deletes a record. The KSF framework standardises this
with a two-level dispatch pattern.

### Hook Names

| Hook Name | Dispatch | Purpose |
|---|---|---|
| `<module>_<action>_<recordType>` | `hook_invoke_all` | Targeted — only interested modules implement |
| `ksf_crud_event` | `hook_invoke_all` | Broadcast — all modules receive the full payload |

**Actions**: `created`, `updated`, `deleted`

### Emitter Pattern (service or page script)

Use `Ksfraser\Traits\CrudEventEmitterTrait` in any service class:

```php
use Ksfraser\Traits\CrudEventEmitterTrait;

class CalendarService {
    use CrudEventEmitterTrait;

    public function createEntry(array $data): int {
        $id = $this->repo->insert($data);
        $this->emitCreated('calendar', 'entry', $id, $data);
        return $id;
    }
}
```

### Listener Pattern (in hooks.php)

```php
class hooks_ksf_FA_SomeModule extends hooks {

    // Specific listener — only fires for calendar_created_entry
    function calendar_created_entry(&$payload, $opts = []) {
        $entryId = $payload['record_id'];
        // create a related record in this module
    }

    // Generic listener — catches all CRUD events from any module
    function ksf_crud_event(&$payload, $opts = []) {
        if ($payload['action'] === 'deleted' && $payload['module'] === 'crm') {
            // clean up related data
        }
    }
}
```

### Payload Structure

```php
$payload = [
    'action'      => 'created',        // string: created|updated|deleted
    'module'      => 'calendar',       // string: module slug
    'record_type' => 'entry',          // string: record type slug
    'record_id'   => 42,               // int|string: primary key
    'data'        => [...],            // array: additional context
];
```

### Comparison: FA Native vs KSF CRUD Events

| Aspect | FA `db_prewrite`/`db_postwrite` | KSF `ksf_crud_event` |
|--------|----------------------------------|----------------------|
| Scope | Core FA tables (`0_debtors_master`, etc.) | Any module's custom tables |
| Module tables | Not fired (bypassed) | Primary use case |
| Granularity | Table-level | Record-type + action |
| Trait available | No | `CrudEventEmitterTrait` |

### See Also

- `ksfraser/traits` — `Ksfraser\Traits\CrudEventEmitterTrait`
- `doc/templates/hooks-template.php` — ready-to-copy hooks.php with CRUD stubs

---

## FA install.sql / Schema Convention

- Use `@TB_PREF@` as placeholder in SQL files (e.g. `@TB_PREF@fa_cal_entries`)
- FA's `activate_extension()` → `update_databases()` substitutes automatically
- Manual substitute for testing: `sed 's/@TB_PREF@/0_/g' install.sql | mysql ...`

## CRM Tag Type Constants

The CRM module defines tag types extending FA's `0_tags` + `0_tag_associations` tables. These are managed via `ksf_FA_CRM/pages/crm_tags.php` (not FA's `admin/tags.php`).

| Constant | Value | Entity | DB Table |
|----------|-------|--------|----------|
| `TAG_CUSTOMER` | 3 | Customer | `debtors_master` |
| `TAG_CONTACT` | 4 | Contact | `crm_contacts` |
| `TAG_OPPORTUNITY` | 5 | Opportunity | `crm_opportunities` |
| `TAG_LEAD` | 6 | Lead | `crm_leads` |
| `TAG_COMMUNICATION` | 7 | Communication | `crm_communications` |

Usage in pages (uses FA's existing tag helpers):
```php
include_once($path_to_root . "/admin/db/tags_db.inc");
include_once($path_to_root . "/modules/ksf_FA_CRM/includes/crm_tags.inc");

// Load existing tags
$tags_result = get_tags_associated_with_record(TAG_CUSTOMER, $entity_id);
$tagids = array();
while ($tag = db_fetch($tags_result))
    $tagids[] = $tag['id'];
$_POST['entity_tags'] = $tagids;

// Render tag selector
tag_list_row(_("Tags:"), 'entity_tags', null, TAG_CUSTOMER, true);

// Save
update_tag_associations(TAG_CUSTOMER, $entity_id, $_POST['entity_tags']);
```

## CRM Module Split

The monolith `ksf_CRM` has been split into two repositories:

| Repository | Type | Namespace | Contents |
|-----------|------|-----------|---------|
| `ksf_CRM` | Business logic | `Ksfraser\CRM\*` | Entities, services, events — no FA deps |
| `ksf_FA_CRM` | FA adapter | `Ksfraser\FA\CRM\*` | hooks.php, pages/, includes/, sql/ |

## Dependencies

- **FrontAccounting 2.4+**
- **MariaDB 10.5+**
- **PHP 7.4** (hard constraint — no PHP 8+ syntax)
