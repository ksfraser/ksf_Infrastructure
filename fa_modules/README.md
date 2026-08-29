Module directory for FA

## UAT deployment notes

- `fa_modules/` is bind-mounted into the `ksf-fa` container (podman, user
  `kevin`) at `/var/www/html/modules`; live at `http://localhost:8080/`.
- The module dirs here are **gitignored runtime artifacts**. Canonical source
  is each dev repo (`/home/kevin/Documents/ksf_FA_*`). Deploy = copy source
  files from dev into the matching `fa_modules/<module>` and re-probe.
- Container opcache revalidates by mtime (no restart needed); confirm with a
  curl probe that previously-broken pages return 200 with no `Fatal error`.

### Square / Woo UAT source (2026-08-29)
- `ksf_FA_Square/hooks.php` and `ksf_FA_Woocommerce/hooks.php` = dev `main`
  (External-section menu registration).
- `ksf_FA_Woocommerce/public/index.php` = dev `main` bootstrap
  (`$path_to_root = "../../.."; require_once $path_to_root . '/includes/session.inc';`).

### Square runtime packages (import-staging / staging-dto)
- `fa_modules/ksf_staging_dto/` = copy of `/home/kevin/Documents/ksf_staging_dto`.
- `fa_modules/ksf_FA_Square/vendor/ksfraser/import-staging` ->
  `../../../ksf_FA_ImportStagingProcessing`
  (relative so it resolves inside the `/var/www/html/modules` mount).
- `fa_modules/ksf_FA_Square/vendor/ksfraser/staging-dto` ->
  `../../../ksf_staging_dto`.
- PSR-4 mappings added to `vendor/composer/autoload_psr4.php` **and**
  `autoload_static.php`:
  `ksfraser\FrontAccounting\ImportStaging\` and `Ksfraser\StagingDto\`.
- Verify: `php -r 'require ".../ksf_FA_Square/vendor/autoload.php";
  var_dump(class_exists("Ksfraser\\StagingDto\\StagingOrder"));'`
- Confirmed live: Square dashboard/config/export/import, Woo sync + import
  orders/customers, FA root, PAV index/brands/lifecycle-flags all HTTP 200,
  zero fatals.
