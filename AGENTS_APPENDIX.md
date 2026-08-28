# AGENTS_APPENDIX.md — UAT Deployment Process

> Appendix to `AGENTS.md` for the `ksf_Infrastructure` repo. Committed.

## Problem Statement

The UAT container `ksf-fa` runs **PHP 7.4** (`/var/www/html`), while the devel
trees under `~/Documents/ksf_FA_*` are developed on **PHP 8.1** and ship a
`vendor/` + `composer.lock` resolved against PHP 8.1:

- Devel `vendor/` contains **PHPUnit 10** (requires PHP >= 8.1) and, for
  `ksf_FA_Square`, prod deps resolved to 8.1-only versions
  (`symfony/http-foundation` 6.x, `league/csv` 9.28, …).
- rsyncing the full devel tree over the bind mount overwrote the previously
  PHP 7.4-built bind-point vendors. Result: every FA page died with a
  `Parse error` from `phpunit/phpunit/src/Framework/Assert/Functions.php`, and
  Square pages would fatal in `symfony/http-foundation`.

**Rule: never rsync `vendor/` or `composer.lock` from a devel tree into the
UAT bind mount.** Deploy source only, then build the vendor inside the
container with PHP 7.4.

## Deployment Process

```bash
# 1) Source-only deploy (bind mount is the repo's fa_modules/)
for m in ksf_FA_Calendar ksf_FA_Square ksf_FA_Woocommerce \
         ksf_FA_DataIntegrity ksf_FA_ImportStagingProcessing; do
  rsync -a --delete \
    --exclude='vendor' --exclude='composer.lock' \
    --exclude='.git'   --exclude='.phpunit.cache' \
    ~/Documents/$m/ ~/Documents/ksf_Infrastructure/fa_modules/$m/
done

# 2) Build vendor inside the container (PHP 7.4 platform, no dev deps)
podman exec ksf-fa sh -c '
for m in ksf_FA_Square ksf_FA_Woocommerce ksf_FA_DataIntegrity ksf_FA_ImportStagingProcessing; do
  cd /var/www/html/modules/$m
  composer update --no-dev --no-interaction || composer install --no-dev --no-interaction
done'
```

Devel dev tools (PHPUnit ^10) stay in the devel trees only; they never run in
the container. Tests are executed on the host (PHP 8.1):
`php vendor/bin/phpunit --no-coverage` from each devel tree.

## Calendar Caveat

`ksf_FA_Calendar` depends on `ksfraser/ksf-fa-common @dev`, whose
`composer.json` requires `ksfraser/ksf_gpg @dev` — a package that does not
exist on Packagist, so `composer update` cannot resolve Calendar. Deploy
Calendar source-only and leave its previously-built vendor in place, or repair
manually (see below). Fixing the `ksf_gpg` requirement upstream is the
long-term remedy.

## Manual Vendor Repair (for a bind mount already broken by a full rsync)

Only PHPUnit's toolchain (`phpunit/*`, `sebastian/*`) requires PHP >= 8.1. To
restore a broken vendor without a rebuild:

1. Delete the 8.1-only package dirs and drop them from
   `vendor/composer/installed.json` and `installed.php`:
   ```bash
   rm -rf ksf_FA_Calendar/vendor/phpunit ksf_FA_Calendar/vendor/sebastian
   # then remove the matching entries from installed.json / installed.php
   # (installed.php is PHP, not JSON — edit or regenerate, don't json_decode it)
   ```
2. Regenerate the autoloader inside the container (PHP 7.4):
   ```bash
   podman exec ksf-fa sh -c 'cd /var/www/html/modules/ksf_FA_Calendar && composer dump-autoload -o'
   ```
3. For modules whose **prod** deps were resolved to 8.1-only versions
   (observed: `ksf_FA_Square`), re-resolve under the container's PHP 7.4:
   ```bash
   podman exec ksf-fa sh -c 'cd /var/www/html/modules/ksf_FA_Square && composer update --no-dev --no-interaction'
   ```
   This downgrades e.g. `symfony/http-foundation` 6.x → 5.4.x and
   `league/csv` → 9.8.0, both PHP 7.4-compatible.

## Verification After Deploy

```bash
curl -s http://localhost:8080/index.php -o /dev/null -w "%{http_code}\n"     # 200
curl -s -X POST http://localhost:8080/index.php \
  -d 'user_name_entry_field=admin&password=admin&company_login_name=0&SubmitLogin=Login' \
  -o /dev/null -w "%{http_code}\n"                                            # 200

# PHP 7.4 lint sweep of runtime code (tests/ may still use PHP 8-only syntax)
podman exec ksf-fa sh -c '
for m in ksf_FA_Calendar ksf_FA_Square ksf_FA_Woocommerce ksf_FA_DataIntegrity ksf_FA_ImportStagingProcessing; do
  find /var/www/html/modules/$m -name "*.php" -not -path "*/vendor/*" -not -path "*/tests/*" \
    -exec php -l {} \;
done | grep -v "No syntax errors"'

## FA Module Version — `_init/config` vs company `installed_extensions.php`

The `Version:` line in a module's `_init/config` MUST use the FA `2.4.X-Y` scheme
(e.g. `2.4.3-1`). Do NOT ship stale values like `1.0.0-0` or `2.0.0`.

FA shows a module as **`Unknown`** on the Admin → Install/Activate extensions screen
when the `Version:` in `_init/config` does not match the *stored* version for that
module in the per-company `company/<id>/installed_extensions.php`.

**When bumping a module version:**
1. Update `Version:` in the module's `_init/config` (source repo), commit + push.
2. Deploy the module to `fa_modules/<module>/` (bind-mounted to
   `/var/www/html/modules/`).
3. Update the matching stored `'version' => '...'` entry for that module in the
   live company file
   (`~/.local/share/containers/storage/volumes/podman_fa_company/_data/0/installed_extensions.php`)
   so FA's version check agrees and the "Unknown" state clears.

Both the source version and the stored company version must be kept in sync.
`_init/config` may be gzip-compressed OR plain text — probe for the gzip magic
bytes (`1f 8b`) before decompressing, and preserve the original format when
editing.
```
