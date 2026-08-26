#!/usr/bin/env python3
"""Option A: nightly full dump of the LOCAL FA database -> WEBHOST clone.

Provides (a) a clean full refresh of the webhost clone (self-heals any drift
from the delta sync) and (b) the offsite backup Kevin wants. The dump excludes
the fa_sync_outbox table and all triggers, so the clone stays a pure data copy
(no recursive capture on the webhost side). A rotating local copy is also kept
under /backups (a named volume, NOT the git repo) for safety.

Passwords are passed via MYSQL_PWD (environment) rather than -p, to avoid
exposing them in the process list.
"""
import os
import sys
import glob
import datetime
import subprocess

BACKUP_DIR = os.environ.get("BACKUP_DIR", "/backups")
RETAIN = int(os.environ.get("BACKUP_RETAIN_DAYS", "7"))


def env(name, default=None):
    v = os.environ.get(name)
    return v if v not in (None, "") else default


LOCAL_HOST = env("LOCAL_DB_HOST", "ksf-mariadb")
LOCAL_PORT = int(env("LOCAL_DB_PORT", "3306"))
LOCAL_NAME = env("LOCAL_DB_NAME", "ksf_fa")
ROOT_PASS = env("LOCAL_DB_ROOT_PASS", env("MARIADB_ROOT_PASSWORD", ""))

WEB_HOST = env("WEBHOST_DB_HOST")
WEB_PORT = int(env("WEBHOST_DB_PORT", "3306"))
WEB_NAME = env("WEBHOST_DB_NAME", "ksf_fa")
WEB_USER = env("WEBHOST_DB_USER")
WEB_PASS = env("WEBHOST_DB_PASS")


def log(m):
    print("[nightly_dump] %s" % m, flush=True)


def local_env():
    e = dict(os.environ)
    e["MYSQL_PWD"] = ROOT_PASS
    return e


def web_env():
    e = dict(os.environ)
    e["MYSQL_PWD"] = WEB_PASS
    return e


def main():
    if not WEB_HOST:
        log("WEBHOST_DB_HOST not set; skipping")
        return 1

    stamp = datetime.datetime.now().strftime("%Y%m%d")
    dump_path = "%s/ksf_fa_%s.sql" % (BACKUP_DIR, stamp)

    # 1. dump local (exclude outbox + triggers)
    dump = subprocess.Popen(
        ["mysqldump", "-h", LOCAL_HOST, "-P", str(LOCAL_PORT), "-u", "root",
         "--single-transaction", "--skip-triggers",
         "--ignore-table=%s.fa_sync_outbox" % LOCAL_NAME,
         "--databases", LOCAL_NAME],
        stdout=open(dump_path, "w"), stderr=subprocess.PIPE, env=local_env())
    _, err = dump.communicate()
    if dump.returncode != 0:
        log("mysqldump failed: %s" % err.decode())
        return 1
    log("dumped %s" % dump_path)

    # 2. load into webhost (drop+recreate tables => clean full refresh)
    load = subprocess.Popen(
        ["mysql", "-h", WEB_HOST, "-P", str(WEB_PORT), "-u", WEB_USER, WEB_NAME],
        stdin=open(dump_path, "r"), stderr=subprocess.PIPE, env=web_env())
    _, err2 = load.communicate()
    if load.returncode != 0:
        log("webhost load failed: %s" % err2.decode())
        return 1
    log("loaded dump into %s/%s" % (WEB_HOST, WEB_NAME))

    # 3. retention
    for f in glob.glob("%s/ksf_fa_*.sql" % BACKUP_DIR):
        age = (datetime.datetime.now()
               - datetime.datetime.fromtimestamp(os.path.getmtime(f))).days
        if age > RETAIN:
            os.remove(f)
            log("purged old dump %s" % f)
    return 0


if __name__ == "__main__":
    sys.exit(main())
