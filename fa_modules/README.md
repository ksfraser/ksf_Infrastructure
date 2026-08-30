Module directory for FA

## UAT deployment notes

- `fa_modules/` is bind-mounted into the `ksf-fa` container (podman, user
  `kevin`) at `/var/www/html/modules`; live at `http://localhost:8080/`.
- The module dirs here are **gitignored runtime artifacts**. Canonical source
  is each dev repo (`/home/kevin/Documents/ksf_FA_*`). Deploy = copy source
  files from dev into the matching `fa_modules/<module>` and re-probe.
- Container opcache revalidates by mtime (no restart needed); confirm with a
  curl probe that previously-broken pages return 200 with no `Fatal error`.

### Integration policy: Packagist-only

Consumers (Square, Woo, ImportStagingProcessing, PAV) pull runtime
dependencies **from Packagist only** — no local path repositories, no
`ksf_staging_dto/` copies, no manual symlinks into `vendor/`.

`ksfraser/fa-classes` (DTO/Repository/Schema for native FA tables, from
`ksf_FA_Classes`) is also on Packagist now (v1.6.0, source ref `1eaf2a0`,
same code as the tagged commit). Consumers `ksf_FA_DataIntegrity` and
`ksf_FA_InvoiceAllocation` were normalized off their `dev-php73` VCS pin /
local path overrides onto Packagist `fa-classes ^1.0`
(`ksf-modules-dao v0.5.1`, `validation v0.1.0` satisfy its requirements).

Installed versions (2026-08-29):

| Package | Version | Used by |
|---------|---------|---------|
| `ksfraser/staging-dto` | v0.0.1 | Square, Woo, ImportStagingProcessing |
| `ksfraser/ksf-fa-common` | v1.0.8 | Square, Woo (PAV locks v1.0.6) |
| `ksfraser/ksf-modules-dao` | v0.3.5 | Woo, ImportStagingProcessing |
| `ksfraser/fa-hooks`, `ksfraser/traits`, `ksfraser/exceptions`, `ksfraser/famock` | — | as required |

`import-staging` is **not a package** (Packagist 404); it is consumed as the
`ksf_FA_ImportStagingProcessing` module via hooks calls (and `staging-dto`
for the DTO layer).

### Canonical ksf_FA_Common (single source of truth)

`ksf_FA_Common` is the platform root. Its `src/autoload.php` registers the
canonical autoloader for `ksfraser\FrontAccounting\Common\` and is loaded
directly (the module itself is not a Composer package). Consumers **must not**
let their `vendor/` copy resolve this namespace.

**Known hazard:** composer's `ClassLoader` registers with `prepend=true`, so
it out-ranks the canonical loader. If a consumer's `vendor/composer`
autoload files still map `ksfraser\FrontAccounting\Common\` to the vendored
ksf-fa-common copy, eager `compat.php` `class_alias()` calls resolve the
vendored copy first; later direct includes of the canonical module files
(e.g. `ksf_FA_Calendar/hooks.php` requires `ComposerDependencies.php`) then
double-declare the same FQCN → fatal.

**Prevention:** after every host-side `composer [install|update]|dump` on
consumer modules, re-strip the ksf-fa-common PSR-4 mapping from
`vendor/composer/autoload_psr4.php` and `autoload_static.php`:

```sh
KSF_FA_COMMON_PATCH=1 php fa_modules/FA_ProductAttributes/scripts/patch-autoload.php
```

The `KSF_FA_COMMON_PATCH=1` env override makes the script apply outside the FA
container (bind-mounted deploys need it host-side). Apply the equivalent
splice to any other consumer that ships `ksf-fa-common` in its vendor tree.
The `ComposerDependencies` class file carries a `defined()` + `class_exists()`
dual guard so raw order no longer matters even if a vendored copy ever loads.

### Square / Woo UAT source (2026-08-29)
- `ksf_FA_Square/hooks.php` and `ksf_FA_Woocommerce/hooks.php` = dev `main`
  (External-section menu registration).
- `ksf_FA_Woocommerce/public/index.php` = dev `main` bootstrap
  (`$path_to_root = "../../.."; require_once $path_to_root . '/includes/session.inc';`).

### Verify
- `php -r 'require "fa_modules/ksf_FA_Square/vendor/autoload.php";
  var_dump(class_exists("Ksfraser\\StagingDto\\StagingOrder"));'` → `true`.
- Probe pages return HTTP 200 with zero `Fatal error`:
  FA root, PAV index/brands/lifecycle-flags, Woo public/index + cron_sync,
  plus any Square route exercised via the FA menu.