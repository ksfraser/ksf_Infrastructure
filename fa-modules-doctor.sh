#!/usr/bin/env bash
# =============================================================================
# fa-modules-doctor.sh - audit and repair PHP 8.1-only Composer packages in the
#                         FA bind-mount module staging area.
# =============================================================================
#
# WHY THIS EXISTS
# ---------------
# Devel trees under ~/Documents/ksf_FA_* run on the HOST, which is PHP 8.1.
# The FA container is PHP 7.4. A devel tree therefore resolves dependencies
# against 8.1, which can install packages the container cannot even parse --
# most commonly PHPUnit 10 (requires PHP >= 8.1).
#
# PHPUnit registers src/Framework/Assert/Functions.php in composer's EAGER
# `files` autoload list, so it is parsed on EVERY request as soon as
# vendor/autoload.php is required. On PHP 7.4 that is a Parse error
# ("unexpected '|'", a union type), which kills every FA page. The failure
# looks unrelated to the module you were working on: FA's exception handler
# then dies on its own "Call to undefined function end_page()", masking the
# real cause.
#
# Per AGENTS_APPENDIX.md the fix is to build vendors with --no-dev inside the
# container. This script audits the staging area and repairs it, rather than
# leaving a site-wide 500 to be rediscovered each time.
#
# USAGE
#   fa-modules-doctor.sh                 # audit + auto-repair
#   fa-modules-doctor.sh --audit         # report only, change nothing
#   fa-modules-doctor.sh --module NAME   # restrict to one module (repeatable)
#
# EXIT: 0 clean, 1 unrepairable problems remain.
# =============================================================================

set -uo pipefail

FA_MODULES_DIR="${FA_MODULES_DIR:-$HOME/Documents/ksf_Infrastructure/fa_modules}"
AUDIT_ONLY=0
ONLY_MODULES=()

RED=$'\033[31m'; GRN=$'\033[32m'; YLW=$'\033[33m'; DIM=$'\033[2m'; RST=$'\033[0m'

usage() { sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit "${1:-0}"; }

while [ $# -gt 0 ]; do
  case "$1" in
    --audit)        AUDIT_ONLY=1 ;;
    --module)       shift; ONLY_MODULES+=("$1") ;;
    -h|--help)      usage 0 ;;
    *) echo "unknown option: $1" >&2; usage 1 ;;
  esac
  shift
done

[ -d "$FA_MODULES_DIR" ] || { echo "no such dir: $FA_MODULES_DIR" >&2; exit 1; }

# Packages whose source is only parseable on PHP 8.0+. Matches the
# phpunit/phpunit 10.x toolchain (sebastian/* and myclabs/deep-copy are pulled
# in only as phpunit's dependency, so removing phpunit removes them too).
PHP81_ONLY_RE='phpunit/phpunit|sebastian/(exporter|diff|environment|global-state|lines-of-code|recursion-context|object-enumerator|type|version)|phar-io/(manifest|version)'

# myclabs/deep-copy 1.x supports PHP 7.1, but deep-copy 2.x requires >= 8.0 and
# 2.x is what phpunit 10 pulls in. Resolve the installed major version instead
# of hardcoding the package, otherwise every phpunit 9 module reads as broken.
# This script deliberately does NOT guess from composer.json constraints: the
# source of truth is what is actually installed under vendor/.

# ---------------------------------------------------------------------------
# Per-module checks
# ---------------------------------------------------------------------------

# installed_versions: report "<name> <version>" for every installed package
installed_versions() {
  local mod="$1" j="$FA_MODULES_DIR/$mod/vendor/composer/installed.json"
  [ -f "$j" ] || return 0
  python3 - "$j" <<'PY' 2>/dev/null
import json, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(0)
for p in sorted(data.get("packages", []), key=lambda x: x.get("name", "")):
    n, v = p.get("name", ""), p.get("version", "")
    if n:
        print(f"{n} {v.lstrip('v')}")
PY
}

# A package is a landmine only if the INSTALLED version cannot be parsed by the
# container's PHP. Deep-copy is the reason this takes versions rather than
# names: 1.x is fine on 7.4, 2.x is not.
version_is_php81_only() {
  case "$1" in
    phpunit/phpunit)          [ "${2%%.*}" -ge 10 ] 2>/dev/null ;;
    myclabs/deep-copy)         [ "${2%%.*}" -ge 2 ] 2>/dev/null ;;
    phar-io/manifest)         [ "${2%%.*}" -ge 2 ] 2>/dev/null ;;
    phar-io/version)          [ "${2%%.*}" -ge 3 ] 2>/dev/null ;;
    sebastian/*)              [ "${2%%.*}" -ge 5 ] 2>/dev/null ;;
    *)                        return 1 ;;
  esac
}

# eager_php81: PHP 8.1-only packages referenced in composer/autoload_files.php
# that are ALSO present in vendor (an entry left behind by a --no-dev rebuild is
# ignored, because nothing can load it).
eager_php81() {
  local mod="$1" f="$FA_MODULES_DIR/$1/vendor/composer/autoload_files.php"
  [ -f "$f" ] || return 0
  # Each line looks like:  'hash' => $vendorDir . '/phpunit/phpunit/src/...',
  # Reduce to "<vendor>/<name>" with python: fragile to express in sed because
  # the literal contains both quotes and a dollar sign.
  python3 - "$f" "$FA_MODULES_DIR/$mod/vendor/composer/installed.json" <<'PY' 2>/dev/null
import json, re, sys
autoload, installed = sys.argv[1], sys.argv[2]
try:
    text = open(autoload, encoding="utf-8", errors="replace").read()
    data = json.load(open(installed))
except Exception:
    sys.exit(0)
vers = {p.get("name", ""): (p.get("version", "") or "").lstrip("v")
        for p in data.get("packages", [])}
pkgs = set()
for m in re.finditer(r"\$vendorDir\s*\.\s*'([^']+)'", text):
    pkgs.add("/".join(m.group(1).lstrip("/").split("/")[:2]))

def bad(p):
    v = vers.get(p)
    if v is None:            # declared in files-autoload but not installed
        return False
    major = v.split(".")[0]
    try:
        major = int(major)
    except ValueError:
        return False
    if p == "phpunit/phpunit":                 return major >= 10
    if p == "myclabs/deep-copy":                return major >= 2
    if p in ("phar-io/manifest",):              return major >= 2
    if p in ("phar-io/version",):               return major >= 3
    if p.startswith("sebastian/"):             return major >= 5
    return False

print("\n".join(sorted(f"{p} {vers[p]}" for p in pkgs if bad(p))))
PY
}

# installed_php81: same verdict across the whole vendor tree, eager or not.
# An installed-but-not-eager 8.1 package is harmless today and only matters if
# some future code path starts autoloading it, so it is reported, not fixed.
installed_php81() {
  installed_versions "$1" | while read -r name ver; do
    version_is_php81_only "$name" "$ver" && echo "$name $ver"
  done
}

# lock_pkg_version <lockfile> <package> -> version string, or empty if absent
lock_pkg_version() {
  python3 - "$1" "$2" <<'PY' 2>/dev/null
import json, sys
try:
    d = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(0)
for sec in ("packages", "packages-dev"):
    for p in d.get(sec, []):
        if p.get("name") == sys.argv[2]:
            print(p.get("version", ""))
            sys.exit(0)
PY
}

# lock_satisfies <composer.json> <package> <version> -> 0 if the constraint
# accepts the version. Deliberately minimal: it only has to answer the one
# question the refresh step asks (does composer.json's ^X.Y allow this tag?),
# so a wrong "no" just means we skip the refresh and composer reports the
# mismatch itself.
lock_satisfies() {
  python3 - "$1" "$2" "$3" <<'PY' 2>/dev/null
import json, re, sys
try:
    d = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(1)
ver = (sys.argv[3] or "").lstrip("v")
if not ver or not re.match(r"^\d+\.\d+", ver):
    sys.exit(1)                       # dev-* / branch: not a stable tag
parts = ver.split(".")
try:
    want = (int(parts[0]), int(parts[1]))
except ValueError:
    sys.exit(1)
for sec in ("require", "require-dev"):
    c = d.get(sec, {}).get(sys.argv[2])
    if not c:
        continue
    m = re.match(r"\^(\d+)\.(\d+)", c)
    if m and (want[0], want[1]) >= (int(m.group(1)), int(m.group(2))):
        sys.exit(0)
sys.exit(1)
PY
}

# ensure_platform_pin <composer.json> <php-version>
#
# The root cause of every "works on my machine" FA 500: a module whose
# composer.json says `php: >=7.4` but declares no `config.platform.php` gets its
# dependencies resolved against whatever PHP runs `composer update` -- here, the
# host's 8.1. Composer then writes a platform_check.php demanding 8.1 and
# installs packages whose source PHP 7.4 cannot parse. Pinning the platform makes
# "the container's PHP" part of the resolution, so a 7.4 vendor is the only
# thing Composer can build.
ensure_platform_pin() {
  local cj="$1" want="$2" have
  have=$(python3 -c "
import json,sys
try: print(json.load(open(sys.argv[1])).get('config',{}).get('platform',{}).get('php',''))
except Exception: print('')" "$cj")
  if [ "$have" = "$want" ]; then return 0; fi
  if [ -n "$have" ]; then
    echo "  ${YLW}note${RST} platform pin is $have, container is $want -- leaving the pin alone"
    return 0
  fi
  echo "  ${YLW}no config.platform.php${RST} (resolves against the host PHP, not the container)"
  if [ "$AUDIT_ONLY" -eq 1 ]; then return 0; fi
  python3 - "$cj" "$want" <<'PINPY'
import json, re, sys
p = sys.argv[1]
raw = open(p).read()
d = json.loads(raw)
d.setdefault("config", {}).setdefault("platform", {})["php"] = sys.argv[2]
m = re.search(r"\n( +)\S", raw)
step = len(m.group(1)) if m else 4
out = json.dumps(d, indent=step, ensure_ascii=False)
if raw.endswith("\n"):
    out += "\n"
open(p, "w").write(out)
print("  -> pinned config.platform.php = " + sys.argv[2] + " in " + p)
PINPY
}

# needs_platform_reresolve <module>
#
# True when the installed vendor holds a package whose declared php floor is
# strictly above the container runtime. `install --no-dev` cannot fix that: the
# lock already names the bad version, so Composer faithfully reinstalls it.
# Only an unambiguous floor ("^8.1", ">=8.0") counts, so this never fires on a
# package that merely *permits* 8.x.
needs_platform_reresolve() {
  local mod="$1" j="$FA_MODULES_DIR/$mod/vendor/composer/installed.json"
  [ -f "$j" ] || return 0
  python3 - "$j" "$CONTAINER_PHP_ID" <<'RERESOLVE'
import json, re, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(1)                      # unreadable -> let composer rebuild
target = int(sys.argv[2])
for p in data.get("packages", []):
    c = (p.get("require") or {}).get("php")
    if not c:
        continue
    if re.search(r"\|\||\^7|~7|>= *7\.", c):
        continue                     # admits 7.x -> fine
    m = re.match(r"^(?:>=|>)?\s*(\d+)\.(\d+)", c)
    if not m:
        continue
    floor = int(m.group(1)) * 10000 + int(m.group(2)) * 100
    if floor > target:
        print("      %s %s requires php %s" % (p["name"], p.get("version", ""), c))
        sys.exit(0)
sys.exit(1)
RERESOLVE
}

repair_module() {
  local mod="$1" dir="$FA_MODULES_DIR/$1"
  [ -d "$dir" ] || { echo "  $mod: not present, skipped"; return 0; }

  if [ ! -f "$dir/composer.json" ]; then
    echo "  $mod: no composer.json, nothing to do"
    return 0
  fi

  # A lock that is out of sync with composer.json cannot be installed from.
  # Do NOT "fix" it with a blind `composer update`: that re-resolves prod
  # versions and can pull 8.1-only releases into a 7.4 target.
  if [ ! -f "$dir/composer.lock" ]; then
    echo "  $mod:${YLW} no composer.lock${RST} - the lock is what makes the build reproducible."
    echo "      Build it in the dev tree and redeploy:"
    echo "        (cd ~/Documents/$mod && composer update --no-dev)"
    return 1
  fi

  # The lock is the input to --no-dev. If the dev tree has been fixed since the
  # last deploy, the staged lock is the stale one and `install` will either fail
  # or (worse) silently succeed against a dev-master that composer.json no longer
  # allows. Prefer the dev tree's lock when it satisfies the constraint.
  local devlock="$HOME/Documents/$mod/composer.lock"
  if [ -f "$devlock" ]; then
    local devver deployver
    devver=$(lock_pkg_version "$devlock" ksfraser/ksf-fa-common)
    deployver=$(lock_pkg_version "$dir/composer.lock" ksfraser/ksf-fa-common)
    if [ -n "$devver" ] && [ "$devver" != "$deployver" ] \
       && lock_satisfies "$dir/composer.json" ksfraser/ksf-fa-common "$devver"; then
      echo "  $mod:${YLW} staged lock is stale${RST} (ksf-fa-common $deployver vs dev tree $devver); refreshing"
      cp "$devlock" "$dir/composer.lock"
    fi
  fi

  if needs_platform_reresolve "$mod"; then
    echo "  $mod: installed vendor has packages that exclude PHP $CONTAINER_PHP"
    ensure_platform_pin "$dir/composer.json" "$CONTAINER_PHP"
    local devjson="$HOME/Documents/$mod/composer.json"
    [ -f "$devjson" ] && ensure_platform_pin "$devjson" "$CONTAINER_PHP"
    echo "  $mod: re-resolving against the pinned platform ..."
    if ! ( cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 \
             composer update --no-dev --no-interaction ) >/tmp/fa-doctor.$$ 2>&1; then
      echo "  $mod:${RED} re-resolve failed:${RST}"
      sed -n '1,14p' /tmp/fa-doctor.$$ | sed 's/^/      /'
      return 1
    fi
    echo "  $mod:${GRN} re-resolved${RST}"
    return 0
  fi

  echo "  $mod: rebuilding vendor with --no-dev ..."
  if ( cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 \
        composer install --no-dev --no-interaction ) >/tmp/fa-doctor.$$ 2>&1; then
    local left removed
    left=$(eager_php81 "$mod")
    removed=$(grep -c 'Removing' /tmp/fa-doctor.$$ 2>/dev/null || echo 0)
    if [ -n "$left" ]; then
      echo "  $mod:${RED} still eager-loading:${RST} $(echo "$left" | tr '\n' ' ')"
      return 1
    fi
    echo "  $mod:${GRN} repaired${RST} ($removed packages removed)"
    return 0
  fi

  echo "  $mod:${RED} composer install failed:${RST}"
  sed -n '1,12p' /tmp/fa-doctor.$$ | sed 's/^/      /'
  echo "      The lock is likely out of date with composer.json (e.g. a package"
  echo "      locked as dev-master but constrained to ^1.0). Fix the lock in the"
  echo "      devel tree and redeploy; do NOT force composer update here."
  return 1
}

# ---------------------------------------------------------------------------
# ensure_platform_pin <composer.json> <php-version>
#
# The root cause of every "works on my machine" FA 500: a module whose
# composer.json says `php: >=7.4` but declares no `config.platform.php` gets its
# dependencies resolved against whatever PHP runs `composer update` -- here, the
# host's 8.1. Composer then writes a platform_check.php demanding 8.1 and
# installs packages whose source PHP 7.4 cannot parse. Pinning the platform makes
# "the container's PHP" part of the resolution, so a 7.4 vendor is the only
# thing Composer can build.
ensure_platform_pin() {
  local cj="$1" want="$2" have
  have=$(python3 -c "
import json,sys
try: print(json.load(open(sys.argv[1])).get('config',{}).get('platform',{}).get('php',''))
except Exception: print('')" "$cj")
  if [ "$have" = "$want" ]; then return 0; fi
  if [ -n "$have" ]; then
    echo "  ${YLW}note${RST} platform pin is $have, container is $want -- leaving the pin alone"
    return 0
  fi
  echo "  ${YLW}no config.platform.php${RST} (resolves against the host PHP, not the container)"
  if [ "$AUDIT_ONLY" -eq 1 ]; then return 0; fi
  python3 - "$cj" "$want" <<'PINPY'
import json, re, sys
p = sys.argv[1]
raw = open(p).read()
d = json.loads(raw)
d.setdefault("config", {}).setdefault("platform", {})["php"] = sys.argv[2]
m = re.search(r"\n( +)\S", raw)
step = len(m.group(1)) if m else 4
out = json.dumps(d, indent=step, ensure_ascii=False)
if raw.endswith("\n"):
    out += "\n"
open(p, "w").write(out)
print("  -> pinned config.platform.php = " + sys.argv[2] + " in " + p)
PINPY
}

# needs_platform_reresolve <module>
#
# True when the installed vendor holds a package whose declared php floor is
# strictly above the container runtime. `install --no-dev` cannot fix that: the
# lock already names the bad version, so Composer faithfully reinstalls it.
# Only an unambiguous floor ("^8.1", ">=8.0") counts, so this never fires on a
# package that merely *permits* 8.x.
needs_platform_reresolve() {
  local mod="$1" j="$FA_MODULES_DIR/$mod/vendor/composer/installed.json"
  [ -f "$j" ] || return 0
  python3 - "$j" "$CONTAINER_PHP_ID" <<'RERESOLVE'
import json, re, sys
try:
    data = json.load(open(sys.argv[1]))
except Exception:
    sys.exit(1)                      # unreadable -> let composer rebuild
target = int(sys.argv[2])
for p in data.get("packages", []):
    c = (p.get("require") or {}).get("php")
    if not c:
        continue
    if re.search(r"\|\||\^7|~7|>= *7\.", c):
        continue                     # admits 7.x -> fine
    m = re.match(r"^(?:>=|>)?\s*(\d+)\.(\d+)", c)
    if not m:
        continue
    floor = int(m.group(1)) * 10000 + int(m.group(2)) * 100
    if floor > target:
        print("      %s %s requires php %s" % (p["name"], p.get("version", ""), c))
        sys.exit(0)
sys.exit(1)
RERESOLVE
}

repair_module() {
  local mod="$1" dir="$FA_MODULES_DIR/$1"
  [ -d "$dir" ] || { echo "  $mod: not present, skipped"; return 0; }

  if [ ! -f "$dir/composer.json" ]; then
    echo "  $mod: no composer.json, nothing to do"
    return 0
  fi

  # A lock that is out of sync with composer.json cannot be installed from.
  # Do NOT "fix" it with a blind `composer update`: that re-resolves prod
  # versions and can pull 8.1-only releases into a 7.4 target.
  if [ ! -f "$dir/composer.lock" ]; then
    echo "  $mod:${YLW} no composer.lock${RST} - the lock is what makes the build reproducible."
    echo "      Build it in the dev tree and redeploy:"
    echo "        (cd ~/Documents/$mod && composer update --no-dev)"
    return 1
  fi

  # The lock is the input to --no-dev. If the dev tree has been fixed since the
  # last deploy, the staged lock is the stale one and `install` will either fail
  # or (worse) silently succeed against a dev-master that composer.json no longer
  # allows. Prefer the dev tree's lock when it satisfies the constraint.
  local devlock="$HOME/Documents/$mod/composer.lock"
  if [ -f "$devlock" ]; then
    local devver deployver
    devver=$(lock_pkg_version "$devlock" ksfraser/ksf-fa-common)
    deployver=$(lock_pkg_version "$dir/composer.lock" ksfraser/ksf-fa-common)
    if [ -n "$devver" ] && [ "$devver" != "$deployver" ] \
       && lock_satisfies "$dir/composer.json" ksfraser/ksf-fa-common "$devver"; then
      echo "  $mod:${YLW} staged lock is stale${RST} (ksf-fa-common $deployver vs dev tree $devver); refreshing"
      cp "$devlock" "$dir/composer.lock"
    fi
  fi

  if needs_platform_reresolve "$mod"; then
    echo "  $mod: installed vendor has packages that exclude PHP $CONTAINER_PHP"
    ensure_platform_pin "$dir/composer.json" "$CONTAINER_PHP"
    local devjson="$HOME/Documents/$mod/composer.json"
    [ -f "$devjson" ] && ensure_platform_pin "$devjson" "$CONTAINER_PHP"
    echo "  $mod: re-resolving against the pinned platform ..."
    if ! ( cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 \
             composer update --no-dev --no-interaction ) >/tmp/fa-doctor.$$ 2>&1; then
      echo "  $mod:${RED} re-resolve failed:${RST}"
      sed -n '1,14p' /tmp/fa-doctor.$$ | sed 's/^/      /'
      # `--no-dev` skips INSTALLING require-dev, not RESOLVING it, so an
      # unresolvable dev constraint still fails the whole update. This is the
      # single most common reason a 7.4-pinned re-resolve dies.
      if grep -q 'phpunit/phpunit\[10' /tmp/fa-doctor.$$; then
        echo "      ${YLW}known blocker:${RST} require-dev pins phpunit ^10, which needs PHP >= 8.1."
        echo "      PHPUnit 9.6 is the last line that supports PHP 7.3+. In the dev tree:"
        echo "        composer require --dev \"phpunit/phpunit:^9.6\" --no-update"
      fi
      if grep -q 'ksfraser/ksf-calendar.*requires php' /tmp/fa-doctor.$$; then
        echo "      ${YLW}known blocker:${RST} the ksf-calendar library package declares php >= 8.0,"
        echo "      so ksf_Calendar_UI cannot be built for a 7.4 target at all until that"
        echo "      constraint is relaxed on the library side."
      fi
      return 1
    fi
    echo "  $mod:${GRN} re-resolved${RST}"
    return 0
  fi

  echo "  $mod: rebuilding vendor with --no-dev ..."
  if ( cd "$dir" && COMPOSER_ALLOW_SUPERUSER=1 \
        composer install --no-dev --no-interaction ) >/tmp/fa-doctor.$$ 2>&1; then
    local left removed
    left=$(eager_php81 "$mod")
    removed=$(grep -c 'Removing' /tmp/fa-doctor.$$ 2>/dev/null || echo 0)
    if [ -n "$left" ]; then
      echo "  $mod:${RED} still eager-loading:${RST} $(echo "$left" | tr '\n' ' ')"
      return 1
    fi
    echo "  $mod:${GRN} repaired${RST} ($removed packages removed)"
    return 0
  fi

  echo "  $mod:${RED} composer install failed:${RST}"
  sed -n '1,12p' /tmp/fa-doctor.$$ | sed 's/^/      /'
  echo "      The lock is likely out of date with composer.json (e.g. a package"
  echo "      locked as dev-master but constrained to ^1.0). Fix the lock in the"
  echo "      devel tree and redeploy; do NOT force composer update here."
  return 1
}

# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

FA_CONTAINER="${FA_CONTAINER:-$(podman ps --format '{{.Names}}' 2>/dev/null | grep -E 'fa$' | head -1)}"

if [ "$AUDIT_ONLY" -eq 1 ]; then MODE="AUDIT ONLY (no changes)"; else MODE="REPAIR"; fi
echo "fa-modules-doctor  dir=$FA_MODULES_DIR  mode=$MODE"

CONTAINER_PHP=""
if [ -n "$FA_CONTAINER" ]; then
  CONTAINER_PHP=$(podman exec "$FA_CONTAINER" php -r 'echo PHP_VERSION;' 2>/dev/null)
  [ -n "$CONTAINER_PHP" ] || { echo "${RED}container $FA_CONTAINER has no working php; cannot verify${RST}"; exit 1; }
  CONTAINER_PHP_ID=$(podman exec "$FA_CONTAINER" php -r 'echo PHP_VERSION_ID;' 2>/dev/null)
  echo "target runtime: $FA_CONTAINER  PHP $CONTAINER_PHP (id $CONTAINER_PHP_ID)"
else
  echo "${RED}no *fa container found; the runtime probe is the authoritative check, so refusing to guess${RST}"
  echo "  start the container, or set FA_CONTAINER=<name>"
  exit 1
fi
echo

# Discover candidates: any module with a vendor dir, optionally filtered.
if [ ${#ONLY_MODULES[@]} -gt 0 ]; then
  CANDIDATES=("${ONLY_MODULES[@]}")
else
  CANDIDATES=()
  for d in "$FA_MODULES_DIR"/*/; do
    [ -d "$d/vendor" ] && CANDIDATES+=("$(basename "$d")")
  done
fi

# ---------------------------------------------------------------------------
# Pass 1 (authoritative): ask the target runtime to load each autoloader.
#
# This is the only check that cannot produce a false negative. It catches every
# mechanism by which a host-resolved vendor breaks PHP 7.4:
#   - eager `files` autoload of a package using PHP 8 syntax (parse error)
#   - vendor/composer/platform_check.php demanding a newer PHP (E_USER_ERROR,
#     which Composer's own generator writes when a prod dependency is resolved
#     for a newer platform than the target)
#   - a missing/renamed package the autoloader references
# ---------------------------------------------------------------------------
probe_container() {
  local mod="$1"
  podman exec "$FA_CONTAINER" php -d display_errors=1 -d error_reporting=E_ALL \
    -r "require '/var/www/html/modules/$mod/vendor/autoload.php'; echo 'PROBE_OK';" 2>&1
}

# module_active <module> -> prints "active" / "inactive" / "unknown"
#
# Urgency depends on this. An active module whose autoloader fatals will take
# the site down the moment any of its hook methods run; an inactive one is a
# trap waiting for whoever activates it next.
module_active() {
  podman exec "$FA_CONTAINER" php -r '
    $f = "/var/www/html/company/0/installed_extensions.php";
    if (!is_readable($f)) { echo "unknown"; return; }
    include $f;
    $p = $argv[1];
    foreach ($installed_extensions as $e) {
      if (($e["package"] ?? "") === $p) {
        $a = $e["active"] ?? false;
        echo ($a === false || $a === "" || $a === 0 || $a === "0") ? "inactive" : "active";
        return;
      }
    }
    echo "not-registered";' "$1" 2>/dev/null || echo "unknown"
}

NEEDS_REPAIR=()
ACTIVE_BREAK=()
for mod in "${CANDIDATES[@]}"; do
  out=$(probe_container "$mod")
  if [[ "$out" == *PROBE_OK* ]]; then
    continue
  fi
  # Trim to the single most informative line.
  reason=$(printf '%s\n' "$out" | grep -iE "parse error|platform_check|does not exist|not found|unterminated" | head -1)
  [ -n "$reason" ] || reason=$(printf '%s\n' "$out" | grep -v '^$' | tail -1)
  state=$(module_active "$mod")
  case "$state" in
    active)
      echo "${RED}BREAKING NOW${RST} $mod  ${DIM}(active extension)${RST}"
      ACTIVE_BREAK+=("$mod") ;;
    *)
      echo "${RED}WILL BREAK${RST}    $mod  ${DIM}($state - fatal the day it is activated)${RST}" ;;
  esac
  echo "    ${DIM}${reason:0:150}${RST}"
  NEEDS_REPAIR+=("$mod")
done

# ---------------------------------------------------------------------------
# Pass 2 (advisory): packages installed that a future code path could autoload.
# ---------------------------------------------------------------------------
LATENT=0
for mod in "${CANDIDATES[@]}"; do
  case " ${NEEDS_REPAIR[*]-} " in *" $mod "*) continue ;; esac
  inst=$(installed_php81 "$mod")
  [ -n "$inst" ] || continue
  # One compact line: these are dev-tooling leftovers (phar-io, sebastian 5.x)
  # that sit in vendor but are not in any files-autoload list. Harmless today,
  # but they are the residue of a host-PHP install and mark the module as one
  # whose vendor was never rebuilt for the container.
  printf '%s' "$inst" | sed -E 's/^[^ ]+ //' | sort -V | tr '\n' ' ' \
    | sed "s/^/${DIM}latent      $mod  php8 pkgs present but never autoloaded: /; s/\$/${RST}/"
  echo
  LATENT=$((LATENT+1))
done

echo
if [ ${#NEEDS_REPAIR[@]} -eq 0 ]; then
  echo "${GRN}All $(( ${#CANDIDATES[@]} )) autoloaders load cleanly on PHP $CONTAINER_PHP.${RST}"
  [ "$LATENT" -gt 0 ] && echo "${DIM}($LATENT module(s) carry PHP 8 packages that nothing autoloads today)${RST}"
  exit 0
fi

if [ ${#ACTIVE_BREAK[@]} -gt 0 ]; then
  echo "${RED}${#ACTIVE_BREAK[@]} ACTIVE extension(s) cannot load on PHP $CONTAINER_PHP:${RST} ${ACTIVE_BREAK[*]}"
fi
if [ "$AUDIT_ONLY" -eq 1 ]; then
  echo "${YLW}Re-run without --audit to repair.${RST}"
  exit 1
fi

# ---------------------------------------------------------------------------
# Repair
# ---------------------------------------------------------------------------
echo
FAILED=()
for mod in "${NEEDS_REPAIR[@]}"; do
  repair_module "$mod" || FAILED+=("$mod")
done
rm -f /tmp/fa-doctor.$$

# ---------------------------------------------------------------------------
# Pass 3: re-probe, because "composer exited 0" is not the same as "PHP can
# actually parse and load it". Only this makes the green result trustworthy.
# ---------------------------------------------------------------------------
echo
echo "re-probing in $FA_CONTAINER (PHP $CONTAINER_PHP):"
for mod in "${NEEDS_REPAIR[@]}"; do
  case " ${FAILED[*]-} " in *" $mod "*) continue ;; esac
  out=$(probe_container "$mod")
  if [[ "$out" == *PROBE_OK* ]]; then
    echo "  ${GRN}ok${RST}   $mod"
  else
    echo "  ${RED}FAIL${RST} $mod"
    printf '%s\n' "$out" | grep -v '^$' | tail -2 | sed 's/^/         /'
    FAILED+=("$mod")
  fi
done

echo
if [ ${#FAILED[@]} -gt 0 ]; then
  echo "${RED}Still broken: ${FAILED[*]}${RST}"
  echo "Each one above needs the FIX IN THE DEV TREE, then a redeploy. See the"
  echo "'vendor built for the wrong PHP' section of AGENTS_APPENDIX.md."
  exit 1
fi
echo "${GRN}All repaired.${RST}"
echo "  curl -s -o /dev/null -w '%{http_code}\\n' http://localhost:8090/index.php   # expect 200"
