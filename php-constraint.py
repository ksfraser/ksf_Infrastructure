#!/usr/bin/env python3
"""
Composer `require.php` constraint evaluator for fa-modules-doctor.sh.

WHY THIS EXISTS
---------------
The doctor used to decide "is this installed package PHP 8 only?" with a
hardcoded name+major table:

    phpunit/phpunit  >= 10
    myclabs/deep-copy >= 2
    phar-io/manifest >= 2
    phar-io/version   >= 3
    sebastian/*       >= 5

That is wrong in both directions, because it ignores the constraint each
package actually declares:

  FALSE POSITIVES (flagged, but fine on PHP 7.4)
    phar-io/manifest     2.0.4  ->  php "^7.2 || ^8.0"
    phar-io/version      3.2.1  ->  php "^7.2 || ^8.0"
    sebastian/environment 5.1.5 ->  php ">=7.3"
    sebastian/global-state 5.0.8 -> php ">=7.3"

  FALSE NEGATIVES (missed, but genuinely fatal on PHP 7.4)
    doctrine/instantiator 2.0.0 ->  php "^8.1"     (not in the table at all)
    myclabs/deep-copy      1.14.0 -> php "^8.0"    (major 1, table wanted >= 2)

So the old code reported 30 offending packages across 9 modules when only 4
across 3 modules are real, and the two most dangerous ones were among the
misses. Composer already recorded the authoritative constraint in
vendor/composer/installed.json, so read it instead of guessing.

USAGE
    php-constraint.py --self-test
    php-constraint.py <vendor/composer/installed.json> [target]
    php-constraint.py --eager <autoload_files.php> <installed.json> [target]

`target` is a PHP version ("7.4.33"). It defaults to $FA_PHP_TARGET, then to
7.4.33, which is the FA container runtime. Prints "name version constraint",
one per line, for packages whose constraint EXCLUDES the target.
"""

import json
import os
import re
import sys

OPS = (">=", "<=", "!=", "==", "=", ">", "<", "^", "~")
DEFAULT_TARGET = "7.4.33"


def parse_version(s):
    """'7.4.33' / 'v7.4' / '7' -> (7, 4, 33); missing parts become 0."""
    out = []
    for part in str(s).strip().lstrip("v").split("."):
        m = re.match(r"^\d+", part)
        if not m:
            break
        out.append(int(m.group()))
    while len(out) < 3:
        out.append(0)
    return tuple(out[:3])


def _normalise(part):
    # Composer's canonical form may put a space after the operator
    # (">= 7.4.0", ">= 7"). Glue it back so that whitespace can safely be
    # used as an AND separator instead of splitting ">=" from its operand.
    for op in OPS:
        part = re.sub(re.escape(op) + r"\s+", op, part)
    return part.strip()


def _satisfies_one(version, token):
    token = token.strip()
    if not token:
        return True
    m = re.match(r"^(>=|<=|!=|==|=|>|<|\^|~)?\s*(.+)$", token)
    if not m:
        return True
    op = m.group(1) or "="
    rhs = m.group(2).strip()
    if not rhs:
        return True
    segments = rhs.count(".") + 1

    if op == "^":
        # ^1.2.3 -> >=1.2.3 <2.0.0 ; ^0.2.3 -> >=0.2.3 <0.3.0 ;
        # ^0.0.3 -> >=0.0.3 <0.0.4 ; ^0.0 -> <0.1.0
        low = parse_version(rhs)
        if low[0] > 0:
            high = (low[0] + 1, 0, 0)
        elif segments == 1:
            high = (1, 0, 0)
        elif low[1] > 0:
            high = (0, low[1] + 1, 0)
        else:
            high = (0, 0, low[2] + 1)
        return low <= version < high

    if op == "~":
        # ~1.2.3 -> >=1.2.3 <1.3.0 ; ~1.2 -> >=1.2 <2.0.0 ; ~1 -> <2.0.0
        low = parse_version(rhs)
        high = (low[0] + 1, 0, 0) if segments < 3 else (low[0], low[1] + 1, 0)
        return low <= version < high

    if "*" in rhs:
        head = rhs.split("*")[0]
        low = parse_version(head)
        high = (low[0] + 1, 0, 0) if head.count(".") == 0 else (low[0], low[1] + 1, 0)
        return low <= version < high

    bound = parse_version(rhs)
    return {
        ">=": version >= bound,
        ">": version > bound,
        "<=": version <= bound,
        "<": version < bound,
        "=": version == bound,
        "==": version == bound,
        "!=": version != bound,
    }[op]


def admits(constraint, version=parse_version(DEFAULT_TARGET)):
    """True when `constraint` permits `version`. ORs are '||' (or legacy '|')."""
    if not constraint:
        return True
    for alternative in re.split(r"\|\|?", constraint):
        tokens = [t for t in re.split(r"[,\s]+", _normalise(alternative)) if t]
        if tokens and all(_satisfies_one(version, t) for t in tokens):
            return True
    return False


def load_installed(path):
    with open(path, encoding="utf-8", errors="replace") as fh:
        return json.load(fh).get("packages", [])


def rejects(packages, target=DEFAULT_TARGET, only=None):
    """[(name, version, constraint)] for packages excluding `target`.

    `only` optionally restricts the result to a set of package names.
    """
    version = parse_version(target)
    out = []
    for p in packages:
        name = p.get("name", "")
        if only is not None and name not in only:
            continue
        constraint = (p.get("require") or {}).get("php")
        if constraint and not admits(constraint, version):
            out.append((name, p.get("version", ""), constraint))
    return sorted(out, key=lambda r: r[0])


def eager_packages(autoload_files_path, installed):
    """Package names referenced from composer/autoload_files.php (files autoload).

    An entry that is declared but not installed is ignored, because a
    --no-dev rebuild leaves the entry behind with nothing to load.
    """
    try:
        with open(autoload_files_path, encoding="utf-8", errors="replace") as fh:
            text = fh.read()
    except OSError:
        return set()
    installed_names = {p.get("name") for p in installed}
    names = set()
    for m in re.finditer(r"\$vendorDir\s*\.\s*'([^']+)'", text):
        vendor = m.group(1).lstrip("/").split("/")
        if len(vendor) < 2:
            continue
        name = "/".join(vendor[:2])
        if name in installed_names:
            names.add(name)
    return names


# (constraint, admits 7.4.33) -- the second column is ground truth from
# composer/semver, and each of these appears in a real installed.json.
SELF_TEST = [
    (">=7.3", True), (">= 7", True), (">= 7.4.0", True), (">= 5.3.0", True),
    ("^7.1 || ^8.0", True), ("^7.1||^8.0", True), ("^7.2 || ^8.0", True),
    ("^7.4 || ^8.0", True), ("^7.4", True), ("~5.6|~7.0", True),
    ("~7.2|~8.0", True), ("~7.0", True), ("^7.1", True), ("~7.4.33", True),
    ("", True),
    ("^8.1", False), ("^8.0", False), (">=8.1.2", False), (">=8.0", False),
    ("^8", False), ("8.*", False), ("^0.3", False), ("7.1.*", False),
    ("~1.2.3", False), ("^7.3.0 <7.5.0", True), ("^7.3.0 <7.4.0", False),
    ("!=7.4.33", False), ("<8.0", True), (">=7.4.33", True),
]


def self_test():
    target = parse_version(DEFAULT_TARGET)
    failures = [
        (c, expected, admits(c, target))
        for c, expected in SELF_TEST
        if admits(c, target) is not expected
    ]
    for c, expected, got in failures:
        print("  FAIL %-22r expected %s got %s" % (c, expected, got), file=sys.stderr)
    print("php-constraint self-test: %d/%d passed"
          % (len(SELF_TEST) - len(failures), len(SELF_TEST)))
    return 1 if failures else 0


def main(argv):
    args = argv[1:]
    if "--self-test" in args:
        return self_test()
    if not args:
        print(__doc__.strip(), file=sys.stderr)
        return 2

    eager = False
    if args and args[0] == "--eager":
        eager = True
        args = args[1:]
        if len(args) < 2:
            print("usage: --eager <autoload_files.php> <installed.json> [target]",
                  file=sys.stderr)
            return 2
        autoload_files, installed_path = args[0], args[1]
        target = args[2] if len(args) > 2 else None
    else:
        installed_path = args[0]
        autoload_files = None
        target = args[1] if len(args) > 1 else None

    target = target or os.environ.get("FA_PHP_TARGET") or DEFAULT_TARGET

    try:
        packages = load_installed(installed_path)
    except (OSError, ValueError) as exc:
        print("cannot read %s: %s" % (installed_path, exc), file=sys.stderr)
        return 0  # unreadable -> stay quiet, let composer be the judge

    only = None
    if eager and autoload_files:
        only = eager_packages(autoload_files, packages)
        if not only:
            return 0

    rc = 0
    for name, version, constraint in rejects(packages, target, only):
        print("%s %s requires php %s" % (name, version, constraint))
        rc = 0
    return rc


if __name__ == "__main__":
    sys.exit(main(sys.argv))
