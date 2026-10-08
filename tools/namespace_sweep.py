#!/usr/bin/env python3
"""
Namespace / PSR-4 compliance sweep across every ksf_* module.

FOUR categories are reported, but only ONE is actionable:

  CAPS    (ACTIONABLE)  vendor segment is 'Ksfraser' rather than 'ksfraser'.
  PREFIX  (informational) namespace is not ksfraser\\FrontAccounting\\<Module>\\.
  PSR4    (informational) composer psr-4 root is not the canonical root.
  LAYOUT  (informational) namespace tail vs its directory.

SCOPE, per the user directive (2026-10-08): we are changing Ksfraser to ksfraser
and NOTHING ELSE. PREFIX, PSR4 and LAYOUT are reported for information only and
must NOT be acted on by this sweep. In particular:

  * Do NOT restructure a PSR-4 root to the canonical deep path. If a module has
    "Ksfraser\\": "src/Ksfraser/" it becomes "ksfraser\\": "src/ksfraser/" --
    the depth is preserved.
  * Do NOT rename module namespaces (Ksfraser\\Warehouse is not becoming
    ksfraser\\FrontAccounting\\Warehouse here).
  * Do NOT touch any other vendor segment: KsfCommon\\, Ksf\\HRM\\,
    FrontAccounting\\, KsfPriceBook\\, KsfBankImport\\, KSFII\\, Automattic\\,
    Action_Scheduler\\, OCA\\, OCP\\, PhpXmlRpc\\, Tests\\ are all out of
    scope. Several are vendored third-party code (WordPress core in
    ksf_Infrastructure, Odoo in others) and must never be renamed at all.

Also reports repos that exist on GitHub but are not cloned locally, which is the
long-term TODO list -- a namespace cannot be audited without the source.

Usage:  python3 tools/namespace_sweep.py [--json]
Exit:   0 clean, 1 violations found, 2 tool error.
"""

import json
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Modules to skip: vendored trees, non-PHP projects, and known non-module dirs.
SKIP_DIRS = {"vendor", ".git", "node_modules", "fa_modules", "dist", "build", "tmp"}

# Documented as an ABORTED branch in AGENTS_ARCH.md -- do not develop there.
SKIP_MODULES = {"ksf_payment_destinations"}


def module_dirs():
    """Every local directory that looks like one of our modules."""
    out = []
    for entry in sorted(os.listdir(ROOT)):
        if entry.startswith(".") or entry in SKIP_DIRS:
            continue
        path = os.path.join(ROOT, entry)
        if not os.path.isdir(path):
            continue
        # A module is ksf_* / FA_* (case-insensitive on the ksf/fa prefix).
        if entry in SKIP_MODULES:
            continue
        if re.match(r"^(ksf|fa)_", entry, re.I) or entry.lower().startswith("ksf"):
            out.append(entry)
    return out


def is_fa_module(name):
    return re.match(r"^ksf_FA_", name) is not None


def expected_root(name):
    """Canonical namespace root for a module directory name."""
    if is_fa_module(name):
        return "ksfraser\\FrontAccounting\\" + name[len("ksf_FA_"):]
    return "ksfraser\\" + name[len("ksf_"):] if name.startswith("ksf_") else None


def php_files(base, subdir=None):
    root = os.path.join(base, subdir) if subdir else base
    if not os.path.isdir(root):
        return
    for dirpath, dirnames, filenames in os.walk(root):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            if fn.endswith(".php"):
                yield os.path.join(dirpath, fn)


def check(name):
    """Return a dict of findings for one module directory."""
    base = os.path.join(ROOT, name)
    find = {"module": name, "caps": [], "prefix": [], "layout": [], "psr4": None}

    # --- namespaces actually declared in PHP ---
    declared = {}
    for path in php_files(base):
        try:
            with open(path, encoding="utf-8", errors="ignore") as fh:
                src = fh.read()
        except OSError:
            continue
        for m in re.finditer(r"^\s*namespace\s+([^;{]+)[;{]", src, re.M):
            ns = m.group(1).strip()
            declared.setdefault(ns, []).append(os.path.relpath(path, base))

    find["declared"] = {k: v for k, v in declared.items()}

    root_ns = expected_root(name)

    for ns, files in declared.items():
        first = ns.split("\\")[0]
        rel = sorted(files)[0]

        # 1. vendor casing
        if first != "ksfraser":
            find["caps"].append({"namespace": ns, "file": rel,
                                 "vendor": first})

        # 2. module prefix
        if root_ns and first == "ksfraser":
            # Test namespaces are nested under a Tests/Fakes segment.
            probe = ns
            for suffix in ("\\Tests\\Unit", "\\Tests\\Fake", "\\Tests\\Fakes", "\\Tests"):
                if probe.endswith(suffix):
                    probe = probe[: -len(suffix)]
                    break
            if not (probe == root_ns or probe.startswith(root_ns + "\\")):
                find["prefix"].append({"namespace": ns, "file": rel,
                                       "expected": root_ns})

    # --- composer PSR-4 ---
    cj = os.path.join(base, "composer.json")
    if os.path.isfile(cj):
        try:
            with open(cj, encoding="utf-8") as fh:
                comp = json.load(fh)
        except (OSError, ValueError) as exc:
            find["psr4"] = {"error": str(exc)}
            return find
        prefixes = comp.get("autoload", {}).get("psr-4", {}) or {}
        find["psr4"] = prefixes
        if root_ns and prefixes and (root_ns + "\\") not in prefixes:
            find["psr4_mismatch"] = {"expected": root_ns + "\\", "found": list(prefixes)}

    # --- 3. namespace vs the path its PSR-4 entry maps to ---
    # The rigorous check: for each (prefix -> path) in composer autoload, take
    # the file's path RELATIVE TO THAT PATH and require it to equal the namespace
    # RELATIVE TO THAT PREFIX. Comparing against "src" or "src/<Module>" instead
    # produced false positives for modules whose PSR-4 root already points deep.
    prefixes = find.get("psr4") or {}
    if not isinstance(prefixes, dict) or not prefixes:
        return find

    for ns, files in declared.items():
        for prefix, relpath in prefixes.items():
            if not ns.startswith(prefix) or ns == prefix.rstrip("\\"):
                continue
            want_tail = ns[len(prefix):].strip("\\")
            base_dir = os.path.join(base, relpath.rstrip("/")).replace(os.sep, "/")
            for rel in sorted(files):
                full = os.path.join(base, rel).replace(os.sep, "/")
                if not full.startswith(base_dir + "/"):
                    continue
                actual_tail = os.path.relpath(full, base_dir)
                actual_tail = os.path.dirname(actual_tail).replace(os.sep, "\\")
                if want_tail and actual_tail and want_tail != actual_tail:
                    find["layout"].append({
                        "namespace": ns, "file": rel,
                        "dir": actual_tail, "tail": want_tail, "prefix": prefix,
                    })
                break
            break

    return find


def remote_repos():
    """All GitHub repos for the owner, to find the not-yet-cloned ones."""
    try:
        out = subprocess.run(
            ["gh", "repo", "list", "ksfraser", "--limit", "400", "--json", "name"],
            capture_output=True, text=True, timeout=60, check=True,
        ).stdout
        return {r["name"] for r in json.loads(out)}
    except (subprocess.SubprocessError, ValueError, OSError) as exc:
        print(f"warning: could not list remote repos: {exc}", file=sys.stderr)
        return None


def main():
    as_json = "--json" in sys.argv

    local = module_dirs()
    findings = [check(n) for n in local]

    local_set = set(local)
    # case-insensitive matching: GitHub names differ in case from local dirs
    lower_local = {n.lower(): n for n in local}

    remote = remote_repos()
    not_cloned = []
    if remote is not None:
        for r in sorted(remote):
            if not re.match(r"^(ksf|fa)_", r, re.I) and not r.lower().startswith("ksf"):
                continue
            if r.lower() in lower_local:
                continue
            not_cloned.append(r)

    if as_json:
        print(json.dumps({"local": findings, "not_cloned": not_cloned}, indent=2))
        return 0 if not any(f["caps"] or f["prefix"] or f.get("psr4_mismatch") for f in findings) else 1

    total = 0
    print("=" * 78)
    print("NAMESPACE COMPLIANCE SWEEP")
    print("=" * 78)

    for f in findings:
        caps = f["caps"]
        prefix = f["prefix"]
        layout = f["layout"]
        mismatch = f.get("psr4_mismatch")
        if not caps:
            continue
        total += 1
        vendors = sorted({c["vendor"] for c in caps})
        nss = sorted({c["namespace"] for c in prefix})
        bad_layout = sorted({l["namespace"] + "  ->  " + l["dir"] for l in layout})
        print(f"\n{f['module']}")
        if vendors:
            print(f"  CAPS    vendor {', '.join(vendors)}   ({len(caps)} file(s))")
            print(f"            e.g. {caps[0]['file']}")
        for ns in nss[:6]:
            exp = next(p2["expected"] for p2 in prefix if p2["namespace"] == ns)
            print(f"  PREFIX  {ns}")
            print(f"            expected {exp}\\...")
        if len(nss) > 6:
            print(f"  PREFIX  ... and {len(nss) - 6} more namespace(s)")
        for l in bad_layout[:6]:
            print(f"  LAYOUT  {l}")
        if len(bad_layout) > 6:
            print(f"  LAYOUT  ... and {len(bad_layout) - 6} more")
        if mismatch:
            print(f"  PSR-4   expected {mismatch['expected']}")
            print(f"            found    {mismatch['found']}")

        info = []
        if prefix:
            info.append(f"PREFIX x{len({p['namespace'] for p in prefix})}")
        if layout:
            info.append(f"LAYOUT x{len(layout)}")
        if mismatch:
            info.append("PSR4")
        if info:
            print(f"  (info)  {'  '.join(info)} -- informational, OUT OF SCOPE, do not act")

    print("=" * 78)
    print("ACTIONABLE = vendor casing only (Ksfraser -> ksfraser).")
    print("PREFIX / PSR4 / LAYOUT above are informational -- DO NOT act on them.")
    print("-" * 78)
    if total == 0:
        print("LOCAL: every module already uses a lowercase vendor segment")
    else:
        print(f"LOCAL: {total} module(s) need the vendor-casing rename")
    print(f"SCANNED: {len(local)} local module dirs")

    if not_cloned:
        print(f"\nREMOTE-ONLY ({len(not_cloned)}) -- cannot audit without the source:")
        for r in not_cloned:
            print(f"  {r}")
    else:
        print("\nREMOTE-ONLY: none")

    print("=" * 78)
    return 0 if total == 0 else 1


if __name__ == "__main__":
    sys.exit(main())