# AGENTS_APPENDIX.md — UAT / Integration Deployment Process

> Appendix to `AGENTS.md` for the `ksf_Infrastructure` repo. Committed.

## Problem Statement

The FA container runs **PHP 7.4** (`ksfii_app-fa`, `/var/www/html`), while the
devel trees under `~/Documents/ksf_*` are developed on **PHP 8.1**. Composer runs
on the host, so a vendor built in a devel tree is resolved *for PHP 8.1* unless
the module pins `config.platform.php`.

Two distinct failure modes result, and they look nothing alike:

1. **Dev tooling eagerly autoloaded.** PHPUnit 10 (PHP >= 8.1) registers
   `src/Framework/Assert/Functions.php` in Composer's eager `files` list, so
   merely `require`ing `vendor/autoload.php` parses PHP 8 union syntax. On 7.4
   that is a `Parse error` on **every FA page**. FA's own handler then dies on
   `Call to undefined function end_page()`, which masks the real cause and makes
   it look like an unrelated module broke.
2. **Prod dependencies resolved too new.** `symfony/http-foundation` 6.x,
   `league/csv` 9.28, `doctrine/event-manager` 2.1, `psr/cache` 3.0 all require
   PHP >= 8. Composer detects this at install time and writes
   `vendor/composer/platform_check.php`, which raises `E_USER_ERROR` the instant
   the autoloader is required. Symptom: "Composer detected issues in your
   platform: Your Composer dependencies require a PHP version >= 8.1.2".
   This one is silent on the login page and only fires when the module's own
   hook method lazily requires its autoloader — so an **active** module can sit
   latent for days and then take the site down on an unrelated page.

**Rule: never rsync `vendor/` or `composer.lock` from a devel tree into
`fa_modules/`.** Deploy source only, then build the vendor for the container's
PHP.

## Deployment Process

Two containers run in parallel, both bind-mount the same
`fa_modules/` at `/var/www/html/modules`:

- **rootful (Integration)**: `ksfii_app-fa`, port **8090**, PHP `7.4.33`, runs as root, overlay `FA/ksfii_app`
- **rootless (ksf_fa)**: `ksf-fa`, port **8080**, PHP `7.4.33`, runs as `kevin` via login shell, overlay `FA/ksf_fa`

Activation state is per-instance (different registries). The same vendor tree is
shared; building it for PHP 7.4 fixes both.

```bash
# 1) Source-only deploy (fa_modules/ is bind-mounted to /var/www/html/modules)
for m in ksf_FA_Square ksf_FA_HRM ksf_FA_CRM ksf_FA_EmailManager; do
  rsync -a --delete \
    --exclude='.git/' --exclude='vendor/' --exclude='composer.lock' \
    --exclude='.phpunit.cache' --exclude='node_modules/' \
    --exclude='test-results/' --exclude='playwright-report/' \
    ~/Documents/$m/ ~/Documents/ksf_Infrastructure/fa_modules/$m/
done
```

Step 2 is now automated — see `fa-modules-doctor.sh` below. The important part:

```bash
# 2) Build the vendor for PHP 7.4 (host composer, pinned platform)
cd ~/Documents/ksf_Infrastructure/fa_modules/ksf_FA_Square
composer update --no-dev --no-interaction
```

**`config.platform.php` is the load-bearing setting.** A module whose
`composer.json` says `php: >=7.4` but declares no `config.platform.php` will
silently resolve for whatever PHP runs Composer. `ksf_FA_Square` and
`ksf_FA_Calendar` carry `"config": {"platform": {"php": "7.4.33"}}`; modules
without it are the ones that break. `fa-modules-doctor.sh` adds the pin
automatically when it re-resolves.

Note the container has **no `composer` binary** — run Composer on the host.
That is safe *because* the platform is pinned; the host PHP no longer leaks
into the resolution.

Devel dev tools stay in the devel trees and never run in the container. Tests
run on the host: `vendor/bin/phpunit --no-coverage` from each devel tree.

### Modules with `path` repositories cannot be rebuilt inside `fa_modules/`

Some modules declare Composer `path` repositories that point at **sibling devel
trees** (`../ksf_RBAC`, `../ksf_CRM`, `../Traits`, `../ksfraser/html`). Those
paths do not exist relative to `fa_modules/<module>/`, so step 2 fails outright:

```
The `url` supplied for the path (../ksf_CRM) repository does not exist
```

This cannot be fixed by deleting the `path` repos, because at least
`ksfraser/rbac` is **not on Packagist** (404) — it exists only as the local
`~/Documents/ksf_RBAC` tree. The workaround is to resolve in a scratch directory
that is a *direct child of `~/Documents`* (so `../<repo>` resolves), then install
the resulting vendor into `fa_modules/`:

```bash
# build beside the sibling devel trees
BUILD=~/Documents/<module>__build
rm -rf "$BUILD"; mkdir -p "$BUILD"
cp ~/Documents/<module>/composer.json ~/Documents/<module>/composer.lock "$BUILD"/
( cd "$BUILD" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction )

# install into staging, dereferencing the path-repo symlinks
cd ~/Documents/ksf_Infrastructure/fa_modules/<module>
rm -rf vendor
rsync -aL "$BUILD/vendor/" vendor/
rm -rf "$BUILD"
```

Two things to know about that step:

- **`rsync -aL` (dereference) is required.** Composer symlinks `path` repos into
  `vendor/`. Copied as symlinks they point at `../../../ksf_RBAC` relative to
  `fa_modules/<module>/vendor/`, which resolves nowhere and is unreachable from
  inside the container anyway. Dereferencing makes the staged vendor
  self-contained.
- **Do not use `composer dump-autoload --optimize`** when regenerating the
  autoloader afterwards. An optimize pass emits a PSR-4 classmap warning and
  *drops* classes whose directory casing does not match the namespace (known:
  `Ksfraser\HTML\JS\HtmlJsEventTrait` lives in `.../HTML/js/`), which silently
  removes them from the autoloader. A plain (non-optimized) `dump-autoload`
  matches every other module.

Affected today: `ksf_FA_CRM`. Any new module that adds a `path` repository
inherits this; prefer a Packagist or VCS repository for deployable dependencies
and reserve `path` repos for devel-only conveniences.

## `fa-modules-doctor.sh` — audit and repair

```bash
cd ~/Documents/ksf_Infrastructure
./fa-modules-doctor.sh --audit              # report only, changes nothing
./fa-modules-doctor.sh                      # repair
./fa-modules-doctor.sh --module ksf_FA_Square
FA_DOCTOR_INSTANCE=ksf-fa ./fa-modules-doctor.sh    # target the other pod
```

It probes **both** containers in one run, since they share the vendor tree but
have independent activation state. It reaches the rootless pod from root with
`su - kevin -c` (login shell; a bare `su` leaves `XDG_RUNTIME_DIR=/run/user/0`
and rootless podman refuses to run). Set `FA_DOCTOR_INSTANCE` to choose which
runtime the repair targets, since one `composer update` cannot satisfy two PHP
floors.

The authoritative check is **not** a heuristic about package versions. It asks
the target runtime to load each autoloader:

```bash
podman exec ksfii_app-fa php -r "require '/var/www/html/modules/<m>/vendor/autoload.php';"
su - kevin -c "podman exec ksf-fa php -r \"require '/var/www/html/modules/<m>/vendor/autoload.php';\""
```

That catches every mechanism above at once, with no false negatives from
version-guessing. Failures are ranked by urgency — `BREAKING NOW` for an active
extension, `WILL BREAK` for an inactive one (fatal the day it is activated) —
by reading `company/0/installed_extensions.php` **inside each container**, since
activation is per-instance.

After any rsync or vendor change, run it. A green run is the deploy gate.

## Known blockers the doctor reports but will not paper over

- **`require-dev` still participates in resolution.** `composer update --no-dev`
  skips *installing* dev dependencies, not *resolving* them. A dev constraint
  that the pinned platform cannot satisfy therefore fails the whole update:

  ```
  - phpunit/phpunit[10.0.0, ..., 10.5.x-dev] require php >= 8.1
    -> your php version (7.4.33; overridden via config.platform) does not satisfy
  ```

  Fix in the dev tree: `composer require --dev "phpunit/phpunit:^9.6" --no-update`.
  PHPUnit 9.6 is the last line supporting PHP 7.3+. Several modules still pin
  `^10.0` in `require-dev`, which is fine on the host but blocks any 7.4-pinned
  re-resolve.

- **A lock that disagrees with `composer.json` cannot be installed from.**
  `"ksfraser/ksf-fa-common" is in the lock file as "dev-master" but that does
  not satisfy your constraint "^1.0"` — regenerate the lock in the dev tree and
  redeploy. Do not force `composer update` in `fa_modules/`; it is a bind mount,
  and a throwaway re-resolve there is lost on the next rsync.

- **Phantom requirements.** A package that is not on Packagist and has no
  `path`/`vcs` repository makes `composer update` fail outright, so the vendor
  silently keeps whatever it had. Before assuming a lock is "blocked on a private
  package", check the require actually exists *and* whether the namespace is
  already provided by a sibling module's autoloader — that is how
  `ksfraser/import-staging` turned out to be both unresolvable and unnecessary
  (the `ksfraser\FrontAccounting\ImportStaging\*` classes belong to the
  `ksf_FA_ImportStagingProcessing` module's own vendor).

## Verification After Deploy

```bash
cd ~/Documents/ksf_Infrastructure && ./fa-modules-doctor.sh   # must be green

curl -s http://localhost:8090/index.php -o /dev/null -w "%{http_code}\n"   # 200
curl -s http://localhost:8080/index.php -o /dev/null -w "%{http_code}\n"   # 200

# PHP 7.4 lint sweep of runtime code (tests/ may still use PHP 8-only syntax)
podman exec ksfii_app-fa sh -c '
for m in ksf_FA_Square ksf_FA_HRM ksf_Calendar; do
  find /var/www/html/modules/$m -name "*.php" -not -path "*/vendor/*" -not -path "*/tests/*" \
    -exec php -l {} \;
done | grep -v "No syntax errors"'
```

An empty lint result matters as much as the HTTP 200: a `Parse error` in a
module source file only surfaces when that class is first loaded, so "the login
page works" is not evidence that the module is loadable.

Note: only `/index.php` is a reliable anonymous probe. Module pages return 200
while logged out but 404 for paths that do not exist, so a 404 there says
nothing about module health — use the doctor's in-container autoload probe.

## Known module state

- **`ksf_Calendar` / `ksf_Calendar_UI`** — both fixed. `require-dev` moved to
  PHPUnit `^9.6`, `config.platform.php = 7.4.33` pinned in both, and `phpunit.xml`
  converted from the PHPUnit 10 schema (`<source>`, `cacheDirectory`) to the 9.6
  schema (`<coverage>`, `cacheResultFile`). Both vendors rebuild clean for 7.4 and
  the doctor is green on both pods. `ksf_Calendar` is **active on the rootless
  pod**, so this was a live landmine there, not just a latent one. Six
  `ksf_Calendar` tests were stale against committed source and were corrected:
  `TYPE_FA_USER` is `'fa_user'` (not `'user'`), `individualStatus` defaults to
  `'planned'` (not `null`), and `getFreeBusy()` returns ISO-8601 (`T` separator).
- **`ksf_FA_Calendar`** (the FA module, distinct from the `ksfraser/ksf-calendar`
  library) is platform-pinned and loads cleanly.

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
   live company file **of each pod** — the registries are per-instance, so one pod
   can be "Unknown" while the other is fine:
   - rootful: `FA/ksfii_app/company/0/installed_extensions.php`
   - rootless: `FA/ksf_fa/company/0/installed_extensions.php`

Both the source version and the stored company version must be kept in sync.
`_init/config` may be gzip-compressed OR plain text — probe for the gzip magic
bytes (`1f 8b`) before decompressing, and preserve the original format when
editing.

---

## Architecture-doc hardlinks (cross-repo guidance)

Canonical ecosystem/architecture notes live at `/home/kevin/Documents/` and are
hardlinked (mode 0444) into the FA/WP module repos:

| Doc | Purpose |
|-----|---------|
| `MODULE_DIRECTORY.md` | Ecosystem map — read first |
| `APP_TAB_ARCHITECTURE.md` | Host/child/plugin tab architecture + unified-tabs roadmap |
| `PACKAGIST.md` | Our packagist packages catalogue — check before reinventing |

Rule for every FA/WP related repo: hardlink these at repo root
(`ln -f /home/kevin/Documents/<doc> <repo>/<doc>`), keep mode 0444, and re-run
`ln -f` after any git operation (hardlinks do not survive pull/checkout/clone).
For clone portability, commit plain copies. Full ritual + carrier list:
`APP_TAB_ARCHITECTURE.md` Appendix A/B and `/home/kevin/Documents/AGENTS_APPENDIX.md`.
