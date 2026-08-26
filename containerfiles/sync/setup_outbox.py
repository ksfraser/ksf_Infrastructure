#!/usr/bin/env python3
"""Create the fa_sync_outbox table and per-table change-capture triggers on the
LOCAL MariaDB. Idempotent and safe to run on every container start.

For every user table in the FA database (except a denylist, or limited to
SYNC_TABLES if set), we install AFTER INSERT/UPDATE/DELETE triggers that write
the changed row as JSON into fa_sync_outbox. The sync worker (sync_outbox.py)
replays those rows to the webhost clone.

This is the "claim-and-replay" capture half of Option B. The outbox row carries
the full row as JSON (row_json) and the primary key as JSON (pk_json) so the
worker can build idempotent upserts / deletes without re-reading the source
table.
"""
import os
import pymysql

LOCAL_HOST = os.environ.get("LOCAL_DB_HOST", "ksf-mariadb")
LOCAL_PORT = int(os.environ.get("LOCAL_DB_PORT", "3306"))
LOCAL_NAME = os.environ.get("LOCAL_DB_NAME", "ksf_fa")
ROOT_PASS = os.environ.get("LOCAL_DB_ROOT_PASS", os.environ.get("MARIADB_ROOT_PASSWORD", ""))

DENYLIST = {"fa_sync_outbox", "migrations", "information_schema", "mysql",
            "performance_schema", "sys"}
ALLOW = {t.strip() for t in os.environ.get("SYNC_TABLES", "").split(",") if t.strip()}


OUTBOX_DDL = """
CREATE TABLE IF NOT EXISTS fa_sync_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  tbl VARCHAR(128) NOT NULL,
  op ENUM('I','U','D') NOT NULL,
  row_json JSON NULL,
  pk_json JSON NULL,
  status ENUM('pending','inflight','sent','error') NOT NULL DEFAULT 'pending',
  batch_id INT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  error TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_status (status),
  INDEX ix_tbl (tbl)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"""


def col_list(conn, db, tbl):
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
            "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s ORDER BY ORDINAL_POSITION",
            (db, tbl))
        return [r[0] for r in cur.fetchall()]


def pk_cols(conn, db, tbl):
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE "
            "WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND CONSTRAINT_NAME='PRIMARY' "
            "ORDER BY ORDINAL_POSITION", (db, tbl))
        return [r[0] for r in cur.fetchall()]


def make_trigger(conn, db, tbl, cols, pk, event):
    newold = "NEW" if event in ("INSERT", "UPDATE") else "OLD"
    row_src = "NEW" if event in ("INSERT", "UPDATE") else "OLD"
    op = "I" if event == "INSERT" else "U" if event == "UPDATE" else "D"
    col_pairs = ", ".join("'%s', %s.`%s`" % (c, row_src, c) for c in cols)
    if pk:
        pk_pairs = ", ".join("'%s', %s.`%s`" % (c, newold, c) for c in pk)
    else:
        pk_pairs = "'__pk', %s.`%s`" % (newold, cols[0])
    trig_name = "trg_%s_%s" % (tbl, event[0].lower())
    statements = [
        "DROP TRIGGER IF EXISTS `%s`" % trig_name,
        ("CREATE TRIGGER `%s` AFTER %s ON `%s` FOR EACH ROW "
         "INSERT INTO fa_sync_outbox (tbl, op, row_json, pk_json) "
         "VALUES ('%s', '%s', JSON_OBJECT(%s), JSON_OBJECT(%s))"
         % (trig_name, event, tbl, tbl, op, col_pairs, pk_pairs)),
    ]
    with conn.cursor() as cur:
        for stmt in statements:
            cur.execute(stmt)
    conn.commit()


def main():
    conn = pymysql.connect(host=LOCAL_HOST, port=LOCAL_PORT, user="root",
                           password=ROOT_PASS, autocommit=False)
    try:
        with conn.cursor() as cur:
            cur.execute("CREATE DATABASE IF NOT EXISTS `%s`" % LOCAL_NAME)
        conn.select_db(LOCAL_NAME)
        with conn.cursor() as cur:
            cur.execute(OUTBOX_DDL)
        # grant the app user rights on the outbox (used by the sync worker)
        with conn.cursor() as cur:
            cur.execute("GRANT SELECT, INSERT, UPDATE, DELETE ON `%s`.fa_sync_outbox TO 'ksf_user'@'%%'"
                        % LOCAL_NAME)
            cur.execute("FLUSH PRIVILEGES")
        conn.commit()

        with conn.cursor() as cur:
            cur.execute("SHOW TABLES")
            tables = [r[0] for r in cur.fetchall()]

        trigger_count = 0
        for tbl in tables:
            if tbl in DENYLIST:
                continue
            if ALLOW and tbl not in ALLOW:
                continue
            cols = col_list(conn, LOCAL_NAME, tbl)
            if not cols:
                continue
            pk = pk_cols(conn, LOCAL_NAME, tbl)
            for ev in ("INSERT", "UPDATE", "DELETE"):
                make_trigger(conn, LOCAL_NAME, tbl, cols, pk, ev)
                trigger_count += 1
        print("setup_outbox: outbox table ready; ensured %d triggers across %d tables"
              % (trigger_count, len(tables)), flush=True)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
