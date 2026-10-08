#!/usr/bin/env python3
"""
Vendor-casing fixer: Ksfraser -> ksfraser. NOTHING ELSE.

Per the user directive (2026-10-08) the only permitted change is the vendor
segment. This script will not:

  * restructure a PSR-4 root to the canonical deep path (depth is preserved:
    "Ksfraser\\": "src/Ksfraser/"  ->  "ksfraser\\": "src/ksfraser/")
  * rename any module namespace (Ksfraser\\Warehouse stays Warehouse)
  * touch any other vendor (KsfCommon, Ksf\\HRM, FrontAccounting, KsfPriceBook,
    KsfBankImport, KSFII, Automattic, Action_Scheduler, OCA, OCP, PhpXmlRpc)

Two patterns are rewritten, and only these two:

    Ksfraser\\   -> ksfraser\\      the vendor segment in a namespace/use/FQCN
    Ksfraser/    -> ksfraser/       the vendor segment in an on-disk path

A BARE "Ksfraser" is never rewritten. It is the company name ("Ksfraser Ltd",
composer author fields, prose in docs), not a namespace.

Generated artefacts are skipped so a rebuild does not resurrect the old casing:
vendor/, .git/, node_modules/, fa_modules/, coverage/, build/, .phpunit.cache/,
and *.log / junit.xml / test-results.xml.

Usage:
    fix_vendor_case.py <module-dir> [...]      apply
    fix_vendor_case.py --dry-run <module-dir>  report only

Exits 1 if a module still has Ksfraser\\ or Ksfraser/ after the rewrite.
"""

import os
import re
import subprocess
import sys

SKIP_DIRS = {
    "vendor", ".git", "node_modules", "fa_modules",
    "coverage", "build", ".phpunit.cache", "archive_docs_20251025",
}
SKIP_NAMES = {"junit.xml", "test-results.xml", "composer.lock"}
SKIP_EXT = {".log", ".lock", ".png", ".jpg", ".gif", ".zip", ".gz", ".tar"}

TEXT_EXT = {
    ".php", ".json", ".xml", ".md", ".neon", ".yml", ".yaml",
    ".txt", ".dist", ".inc", ".tpl", ".twig", ".sql", ".sh", ".css", ".js",
}

OLD_SEG = "Ksfraser"
NEW_SEG = "ksfraser"


def should_skip_path(rel):
    parts = rel.split(os.sep)
    return any(p in SKIP_DIRS for p in parts) or os.path.basename(rel) in SKIP_NAMES


def rewrite_text(path):
    """Rewrite only the two permitted patterns. Returns True if changed."""
    with open(path, encoding="utf-8", errors="surrogateescape") as fh:
        original = fh.read()

    # Ksfraser\ and Ksfraser/ only. A bare Ksfraser (no separator) is left alone.
    updated = original.replace(OLD_SEG + "\\", NEW_SEG + "\\")
    updated = updated.replace(OLD_SEG + "/", NEW_SEG + "/")

    if updated == original:
        return False

    with open(path, "w", encoding="utf-8", errors="surrogateescape") as fh:
        fh.write(updated)
    return True


def rewrite_composer_json(path):
    """PSR-4 key. Depth is preserved deliberately -- no canonical-root change."""
    with open(path, encoding="utf-8") as fh:
        original = fh.read()
    # In JSON the key appears as "Ksfraser\\" i.e. Ksfraser + two backslashes.
    updated = original.replace('"%s\\\\\\\\"' % OLD_SEG, '"%s\\\\\\\\"' % NEW_SEG)
    updated = updated.replace('"%s\\\\"' % OLD_SEG, '"%s\\\\"' % NEW_SEG)
    if updated == original:
        return False
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(updated)
    return True


def vendor_dirs(base):
    """Directories literally named Ksfraser that PSR-4 style paths point at."""
    found = []
    for dirpath, dirnames, _ in os.walk(base):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for d in list(dirnames):
            if d == OLD_SEG:
                found.append(os.path.join(dirpath, d))
    return found


def apply_module(name, dry_run=False):
    base = name
    if not os.path.isdir(base):
        print("  skip %-34s (not a directory)" % name)
        return 0

    changed = []
    dirs = vendor_dirs(base)

    for dirpath, dirnames, filenames in os.walk(base):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            if fn in SKIP_NAMES:
                continue
            ext = os.path.splitext(fn)[1]
            if ext not in TEXT_EXT:
                continue
            p = os.path.join(dirpath, fn)
            if should_skip_path(os.path.relpath(p, base)):
                continue
            if dry_run:
                with open(p, encoding="utf-8", errors="surrogateescape") as fh:
                    s = fh.read()
                if (OLD_SEG + "\\") in s or (OLD_SEG + "/") in s:
                    changed.append(p)
            elif rewrite_text(p):
                changed.append(p)

    cj = os.path.join(base, "composer.json")
    if os.path.isfile(cj):
        if dry_run:
            with open(cj, encoding="utf-8") as fh:
                s = fh.read()
            if (OLD_SEG + "\\\\") in s or (OLD_SEG + "\\") in s:
                changed.append(cj)
        elif rewrite_composer_json(cj):
            changed.append(cj)

    if not dry_run:
        for d in dirs:
            target = os.path.join(os.path.dirname(d), NEW_SEG)
            if os.path.isdir(target):
                continue
            # No cwd: d and target are already rooted at the module dir.
            subprocess.run(["git", "-C", base, "mv",
                            os.path.relpath(d, base), os.path.relpath(target, base)],
                           check=True)
            changed.append(d + " (dir rename)")

    # Verify.
    residual = 0
    for dirpath, dirnames, filenames in os.walk(base):
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
        for fn in filenames:
            ext = os.path.splitext(fn)[1]
            if ext not in TEXT_EXT and fn != "composer.json":
                continue
            p = os.path.join(dirpath, fn)
            if should_skip_path(os.path.relpath(p, base)):
                continue
            try:
                with open(p, encoding="utf-8", errors="surrogateescape") as fh:
                    s = fh.read()
            except OSError:
                continue
            residual += s.count(OLD_SEG + "\\") + s.count(OLD_SEG + "/")

    verb = "would change" if dry_run else "changed"
    print("  %-34s %3d file(s) %s, %d vendor dir(s)%s" % (
        name, len(changed), verb, len(dirs),
        "" if dry_run else ", %d residual" % residual if residual else "",
    ))
    return residual


def main():
    args = sys.argv[1:]
    dry = False
    if args and args[0] == "--dry-run":
        dry = True
        args = args[1:]
    if not args:
        print(__doc__)
        return 2

    print("vendor-casing fix: Ksfraser -> ksfraser  (dry-run)" if dry else
          "vendor-casing fix: Ksfraser -> ksfraser")
    residual_total = 0
    for a in args:
        residual_total += apply_module(a, dry_run=dry)

    if not dry and residual_total:
        print("\n%d residual occurrence(s) -- inspect before committing" % residual_total)
    return 1 if (residual_total and not dry) else 0


if __name__ == "__main__":
    sys.exit(main())