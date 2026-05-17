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

## FA install.sql / Schema Convention

- Use `@TB_PREF@` as placeholder in SQL files (e.g. `@TB_PREF@fa_cal_entries`)
- FA's `activate_extension()` → `update_databases()` substitutes automatically
- Manual substitute for testing: `sed 's/@TB_PREF@/0_/g' install.sql | mysql ...`

## Dependencies

- **FrontAccounting 2.4+**
- **MariaDB 10.5+**
- **PHP 7.4** (hard constraint — no PHP 8+ syntax)
