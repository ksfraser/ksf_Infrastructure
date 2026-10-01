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

# Resolve the staging dir relative to this script, not $HOME. The rootful pod is
# driven as root, whose $HOME is /root, so a $HOME-relative default silently
# points at a path that does not exist when the tool is run the "correct" way.
_SELF_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
FA_MODULES_DIR="${FA_MODULES_DIR:-$_SELF_DIR/fa_modules}"
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
# Instances
#
# There is more than one FA pod, and they are NOT interchangeable:
#
#   - the ROOTFUL pod runs as root and owns the rootful podman store
#   - the ROOTLESS pod runs as an ordinary user with its own store
#
# `podman` resolves against whichever store the *calling user* owns, so the
# rootful pod is invisible to the rootless user and vice versa. Any tool that
# assumes one podman namespace silently sees only half the estate.
#
# The two also DIVERGE in ways that matter:
#   - separate per-instance overlay dirs, so separate config_db.php, separate
#     global registry and separate company/<id>/installed_extensions.php
#   - therefore a different set of ACTIVE extensions on each
#
# What they SHARE is the thing that breaks: both bind-mount the same
# fa_modules/ at /var/www/html/modules. A vendor resolved for the wrong PHP is
# therefore a defect in both, and has to be probed in both.
#
# Fields: name | port | overlay dir | podman user ("" = whatever we are)
# ---------------------------------------------------------------------------
ALL_INSTANCES="ksfii_app-fa:8090:FA/ksfii_app:
ksf-fa:8080:FA/ksf_fa:kevin"

INSTANCES=""
POD_PREFIX_CACHE=""

# pod_for <instance> -> prints a working "podman exec" invocation, or fails.
# Tries the current user first, then each configured podman user.
# pod_for <instance> -> prints the prefix that can see it, or fails.
# Cache entries use "." for "current user". Split on newlines only: word
# splitting would tear "su - kevin -c" into four useless pieces.
# Resolved prefix per instance.
#
# Two traps this design avoids:
#  1. POD_OWNER must be populated in the MAIN shell. Every consumer runs inside
#     a command substitution (subshell), so a memo written during a lookup is
#     thrown away before the next call. Rescanning `podman ps` on every single
#     exec floods rootful podman, which intermittently fails to answer and
#     produced phantom "podman cannot see container 'ksfii_app-fa'" reports.
#  2. Never pipe podman into `grep -q`. Under `set -o pipefail`, grep -q exits
#     at the first match and closes the pipe, podman dies with SIGPIPE (141),
#     and the pipeline reports failure -- again randomly. Capture first, match
#     the captured value.
declare -A POD_OWNER=()

# resolve_instance <name>: find the prefix that can see it and record it.
# Call once per instance from the main shell, right after build_pod_cache.
resolve_instance() {
  local want="$1" p names attempt
  for attempt in 1 2 3; do
    while IFS= read -r p; do
      [ -n "$p" ] || continue
      if [ "$p" = "." ]; then
        names=$(podman ps -a --format '{{.Names}}' 2>/dev/null)
      else
        names=$(run_pod "$p" ps -a --format '{{.Names}}' 2>/dev/null)
      fi
      if printf '%s\n' "$names" | grep -qx "$want"; then
        POD_OWNER[$want]="$p"
        return 0
      fi
    done <<< "$POD_PREFIX_CACHE"
  done
  return 1
}

# pod_for <name> -> the resolved prefix. Pure lookup; never calls podman.
pod_for() {
  if [ -n "${POD_OWNER[$1]+set}" ]; then
    echo "${POD_OWNER[$1]}"
    return 0
  fi
  return 1
}

# pod_exec <instance> <podman exec args...>
pod_exec() {
  local inst="$1" p
  shift
  p=$(pod_for "$inst") \
    || { echo "podman cannot see container '$inst'" >&2; return 1; }
  run_pod "$p" exec "$inst" "$@"
}

# The cache holds candidate prefixes in the order they should be tried.
build_pod_cache() {
  # Candidates, one per line: "." means plain `podman` as the current user,
  # then one `su - <user> -c` per other podman owner. A single run can then see
  # both the rootful and the rootless estate.
  #
  # "su -" (login shell), NOT bare "su": bare su keeps root's environment, so
  # XDG_RUNTIME_DIR stays /run/user/0 and rootless podman refuses to run with
  # "XDG_RUNTIME_DIR directory /run/user/0 is not owned by the current user".
  #
  # Skip a prefix naming the user we already are: root running `su - root -c`
  # re-enters a login shell and can lose the rootful podman's socket, which
  # shows up as "visible but not responding to php".
  local me users p
  me=$(id -un)
  users=$(printf '%s\n' "$ALL_INSTANCES" | cut -d: -f4 | grep -v '^$' | sort -u)
  POD_PREFIX_CACHE="."
  for p in $users; do
    [ "$p" = "$me" ] && continue
    POD_PREFIX_CACHE="$POD_PREFIX_CACHE
su - $p -c"
  done
}

# pod_exec <instance> <args...>
# sq <string> -> the string wrapped in POSIX single quotes, with embedded
# single quotes escaped. Used to rebuild one argv as a single `sh -c` string.
sq() { printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"; }

run_pod() {
  # $1 = prefix: "." means current user, or "su - user -c"
  # $2+ = podman subcommand and its args
  #
  # The args are re-quoted into ONE string because `su -c` accepts a single
  # command string. Getting this wrong is subtle: with naive quoting,
  # `--format {{.Names}}` reaches podman as \{\{.Names\}\} and podman rejects it
  # as an unknown format specifier, which looks like "container not found".
  local prefix="$1"
  shift
  if [ "$prefix" = "." ]; then
    podman "$@"
    return $?
  fi
  local cmd="" a
  for a in "$@"; do cmd+=" $(sq "$a")"; done
  eval "$prefix$(sq "podman$cmd")"
}

# instance_port <instance>
instance_port() {
  printf '%s\n' "$ALL_INSTANCES" | awk -F: -v n="$1" '$1==n{print $2}'
}

# discover_instances -> sets the global INSTANCES (newline-separated) to the
# containers podman can actually see. Deliberately NOT called via $(...): a
# command substitution is a subshell, which would discard the POD_OWNER memo
# that resolve_instance populates.
INSTANCES=""
discover_instances() {
  local line name acc=""
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    name=${line%%:*}
    resolve_instance "$name" && acc="${acc:+$acc
}$name"
  done <<< "$ALL_INSTANCES"
  INSTANCES="$acc"
}

# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

if [ "$AUDIT_ONLY" -eq 1 ]; then MODE="AUDIT ONLY (no changes)"; else MODE="REPAIR"; fi
echo "fa-modules-doctor  dir=$FA_MODULES_DIR  mode=$MODE"

build_pod_cache
# Called directly, not via $(...): the POD_OWNER memo must land in this shell.
discover_instances
if [ -z "$INSTANCES" ]; then
  echo "${RED}no FA container is visible to this user${RST}"
  echo "  rootful pod is owned by root; rootless pod by its own user."
  echo "  Add the missing side to ALL_INSTANCES, or re-run as the owning user."
  exit 1
fi

# The repair path targets ONE runtime, because a single `composer update`
# cannot satisfy two different PHP floors. Default to the first healthy
# instance; override with FA_DOCTOR_INSTANCE=<container>.
TARGET_INSTANCE="${FA_DOCTOR_INSTANCE:-}"
ALIVE=""
echo "instances:"
while read -r inst; do
  [ -n "$inst" ] || continue
  pv=$(pod_exec "$inst" php -r 'echo PHP_VERSION;' 2>/dev/null)
  if [ -n "$pv" ]; then
    echo "  ${GRN}ok${RST}      $inst  PHP $pv  http://localhost:$(instance_port "$inst")/"
    ALIVE="${ALIVE:+$ALIVE
}$inst"
  else
    echo "  ${RED}down${RST}    $inst  (visible but not responding to php)"
    INSTANCES=$(printf '%s\n' "$INSTANCES" | grep -vx "$inst")
  fi
done <<< "$INSTANCES"

if [ -z "$ALIVE" ]; then
  echo "${RED}no instance has a working php; nothing can be verified${RST}"
  exit 1
fi
if [ -n "$TARGET_INSTANCE" ]; then
  case "
$ALIVE" in
    *"
$TARGET_INSTANCE
"*) ;;
    *)
      echo "${RED}FA_DOCTOR_INSTANCE=$TARGET_INSTANCE is not a healthy instance${RST}"
      echo "  healthy: $(printf '%s' "$ALIVE" | paste -sd' ' -)"
      exit 1 ;;
  esac
else
  TARGET_INSTANCE=${ALIVE%%$'\n'*}
fi

# What the repair helpers reason about. Both pods run 7.4 today, but derive it
# rather than hardcoding, so a future pod bump does not silently mis-pin.
CONTAINER_PHP=$(pod_exec "$TARGET_INSTANCE" php -r 'echo PHP_VERSION;' 2>/dev/null)
CONTAINER_PHP_ID=$(pod_exec "$TARGET_INSTANCE" php -r 'echo PHP_VERSION_ID;' 2>/dev/null)
echo "  ${DIM}repair target: $TARGET_INSTANCE  PHP $CONTAINER_PHP (id $CONTAINER_PHP_ID)${RST}"
echo "  ${DIM}note: both pods bind-mount $FA_MODULES_DIR at /var/www/html/modules,${RST}"
echo "  ${DIM}      so a broken vendor is a defect in both, and is probed in both.${RST}"
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
# Pass 1 (authoritative): ask each target runtime to load each autoloader.
#
# This is the only check that cannot produce a false negative. It catches every
# mechanism by which a host-resolved vendor breaks PHP 7.4:
#   - eager `files` autoload of a package using PHP 8 syntax (parse error)
#   - vendor/composer/platform_check.php demanding a newer PHP (E_USER_ERROR,
#     which Composer's own generator writes when a prod dependency is resolved
#     for a newer platform than the target)
#   - a missing/renamed package the autoloader references
#
# Probing more than one instance matters because activation state is per
# instance: a module can be an active landmine on one pod and dead code on the
# other, and only the first one is taking the site down.
# ---------------------------------------------------------------------------
probe() {
  local inst="$1" mod="$2"
  pod_exec "$inst" php -d display_errors=1 -d error_reporting=E_ALL \
    -r "require '/var/www/html/modules/$mod/vendor/autoload.php'; echo 'PROBE_OK';" 2>&1
}

# module_active <instance> <module> -> "active" | "inactive" | "not-registered"
#
# Activation is per company AND per instance: each pod has its own
# company/<id>/installed_extensions.php, so this must never be cached across
# instances.
module_active() {
  pod_exec "$1" php -r '
    $f = "/var/www/html/company/0/installed_extensions.php";
    if (!is_readable($f)) { echo "no-registry"; return; }
    include $f;
    foreach ($installed_extensions as $e) {
      if (($e["package"] ?? "") === $argv[1]) {
        $a = $e["active"] ?? false;
        echo ($a === false || $a === "" || $a === 0 || $a === "0") ? "inactive" : "active";
        return;
      }
    }
    echo "not-registered";' "$2" 2>/dev/null || echo "unknown"
}

NEEDS_REPAIR=()
URGENT=()
for mod in "${CANDIDATES[@]}"; do
  broken_anywhere=0
  for inst in $INSTANCES; do
    out=$(probe "$inst" "$mod")
    [[ "$out" == *PROBE_OK* ]] && continue
    broken_anywhere=1
    state=$(module_active "$inst" "$mod")
    case "$state" in
      active)
        tag="${RED}BREAKING NOW${RST}"; URGENT+=("$mod:$inst") ;;
      *)
        tag="${RED}WILL BREAK${RST}   " ;;
    esac
    # No `| head -1` / `| tail -1` here: under `set -o pipefail` the closing
    # end of the pipeline can SIGPIPE the grep, which discards the match we
    # came for. Read the first match with a shell loop instead.
    reason=""
    line=""
    while IFS= read -r line; do
      [ -n "$line" ] || continue
      case "$line" in
        *[Pp]arse\ error*|*platform_check*|*does\ not\ exist*|*not\ found*|*unterminated*)
          reason="$line"; break ;;
      esac
    done <<< "$out"
    if [ -z "$reason" ]; then
      line=""
      while IFS= read -r line; do
        [ -n "$line" ] && reason="$line"
      done <<< "$out"
    fi
    port=$(instance_port "$inst")
    echo "$tag $mod  ${DIM}[$inst :$port  $state]${RST}"
    echo "    ${DIM}${reason:0:130}${RST}"
  done
  [ "$broken_anywhere" -eq 1 ] && NEEDS_REPAIR+=("$mod")
done

# ---------------------------------------------------------------------------
# Pass 2 (advisory): packages installed that a future code path could autoload.
# ---------------------------------------------------------------------------
LATENT=0
for mod in "${CANDIDATES[@]}"; do
  case " ${NEEDS_REPAIR[*]-} " in *" $mod "*) continue ;; esac
  inst=$(installed_php81 "$mod")
  [ -n "$inst" ] || continue
  printf '%s' "$inst" | sed -E 's/^[^ ]+ //' | sort -V | tr '\n' ' ' \
    | sed "s/^/${DIM}latent      $mod  php8 pkgs present but never autoloaded: /; s/\$/${RST}/"
  echo
  LATENT=$((LATENT+1))
done

echo
if [ ${#NEEDS_REPAIR[@]} -eq 0 ]; then
  echo "${GRN}All $(( ${#CANDIDATES[@]} )) autoloaders load on every instance.${RST}"
  [ "$LATENT" -gt 0 ] && echo "${DIM}($LATENT module(s) carry PHP 8 packages that nothing autoloads today)${RST}"
  exit 0
fi

if [ ${#URGENT[@]} -gt 0 ]; then
  echo "${RED}ACTIVE extension(s) that cannot load:${RST} ${URGENT[*]}"
fi
if [ "$AUDIT_ONLY" -eq 1 ]; then
  echo "${YLW}Re-run without --audit to repair.${RST}"
  exit 1
fi

# ---------------------------------------------------------------------------
# Repair. The vendor is shared, so one repair fixes every instance; it is then
# re-probed on all of them.
# ---------------------------------------------------------------------------
echo
FAILED=()
for mod in "${NEEDS_REPAIR[@]}"; do
  repair_module "$mod" || FAILED+=("$mod")
done
rm -f /tmp/fa-doctor.$$

echo
echo "re-probing every instance:"
for mod in "${NEEDS_REPAIR[@]}"; do
  case " ${FAILED[*]-} " in *" $mod "*) continue ;; esac
  line="  "
  for inst in $INSTANCES; do
    out=$(probe "$inst" "$mod")
    if [[ "$out" == *PROBE_OK* ]]; then
      line="$line${GRN}ok${RST}:$inst  "
    else
      line="$line${RED}FAIL${RST}:$inst  "
      FAILED+=("$mod")
    fi
  done
  echo "$line"
done

echo
if [ ${#FAILED[@]} -gt 0 ]; then
  echo "${RED}Still broken: ${FAILED[*]}${RST}"
  echo "Each one above needs the FIX IN THE DEV TREE, then a redeploy. See the"
  echo "'vendor built for the wrong PHP' section of AGENTS_APPENDIX.md."
  exit 1
fi
echo "${GRN}All repaired on all instances.${RST}"
while read -r inst; do
  [ -n "$inst" ] || continue
  echo "  curl -s -o /dev/null -w '%{http_code}\\n' http://localhost:$(instance_port "$inst")/index.php"
done <<< "$INSTANCES"
