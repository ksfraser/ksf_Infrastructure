#!/usr/bin/env python3
"""Option B: replay pending fa_sync_outbox rows from the LOCAL MariaDB to the
WEBHOST clone.

Resumable and idempotent. The local "claim" is a SHORT transaction
(pending -> inflight); the webhost apply is a SEPARATE transaction on the
webhost connection. On success the batch is marked sent; on failure it is
returned to pending (or 'error' once attempts are capped) so the next run
retries. Because every webhost write is an upsert by primary key, re-sending
the same row is safe.

NOTE on the transaction design: we deliberately do NOT wrap the webhost INSERT
inside the local transaction. A single transaction cannot span two database
servers, and holding locks on the local outbox during a WAN round-trip would be
a long-lived transaction (bad). Instead we (1) claim a batch, (2) transmit to
the webhost in the webhost's own transaction, (3) only then mark the batch sent
on the local side.
"""
import os
import sys
import time
import json
import pymysql

BATCH = int(os.environ.get("SYNC_BATCH_SIZE", "2000"))
MAX_ATTEMPTS = int(os.environ.get("SYNC_MAX_ATTEMPTS", "10"))


def env(name, default=None):
    v = os.environ.get(name)
    return v if v not in (None, "") else default


LOCAL = dict(host=env("LOCAL_DB_HOST", "ksf-mariadb"),
             port=int(env("LOCAL_DB_PORT", "3306")),
             user=env("LOCAL_DB_USER", "ksf_user"),
             password=env("LOCAL_DB_PASS", ""),
             db=env("LOCAL_DB_NAME", "ksf_fa"))

WEB = dict(host=env("WEBHOST_DB_HOST"),
           port=int(env("WEBHOST_DB_PORT", "3306")),
           user=env("WEBHOST_DB_USER"),
           password=env("WEBHOST_DB_PASS"),
           db=env("WEBHOST_DB_NAME", "ksf_fa"))


def log(m):
    print("[sync_outbox] %s" % m, flush=True)


def main():
    if not WEB["host"]:
        log("WEBHOST_DB_HOST not set; skipping (will retry next run)")
        return 0

    # --- 1. claim a batch on the LOCAL db (short transaction) ---
    lc = pymysql.connect(autocommit=False, **LOCAL)
    try:
        with lc.cursor() as cur:
            cur.execute(
                "SELECT id, tbl, op, row_json, pk_json FROM fa_sync_outbox "
                "WHERE status='pending' ORDER BY id LIMIT %s FOR UPDATE SKIP LOCKED",
                (BATCH,))
            rows = cur.fetchall()
            if not rows:
                cur.execute("DELETE FROM fa_sync_outbox "
                            "WHERE status='sent' AND created_at < NOW() - INTERVAL 7 DAY")
                lc.commit()
                log("no pending rows; purged old sent rows")
                return 0
            ids = [r[0] for r in rows]
            bid = int(time.time() * 1000) % 1_000_000_000
            cur.execute(
                "UPDATE fa_sync_outbox SET status='inflight', batch_id=%s, attempts=attempts+1 "
                "WHERE id IN %s", (bid, tuple(ids)))
            lc.commit()
        log("claimed batch %d (%d rows)" % (bid, len(rows)))
    except Exception as e:  # pragma: no cover
        lc.rollback()
        log("claim failed: %s" % e)
        return 1
    finally:
        lc.close()

    # --- 2. transmit to WEBHOST (separate transaction) ---
    wc = pymysql.connect(autocommit=False, **WEB)
    try:
        by_tbl = {}
        for r in rows:
            by_tbl.setdefault(r[1], []).append(r)
        for tbl, trows in by_tbl.items():
            cols = set()
            ins = []
            del_pks = []
            for r in trows:
                op, rj, pkj = r[2], r[3], r[4]
                if op in ("I", "U") and rj:
                    ins.append(json.loads(rj))
                    cols.update(json.loads(rj).keys())
                elif op == "D" and pkj:
                    del_pks.append(json.loads(pkj))
            cols = list(cols)
            if not cols and not del_pks:
                continue
            with wc.cursor() as cur:
                if ins:
                    col_sql = ", ".join("`%s`" % c for c in cols)
                    placeholders = ", ".join(["%s"] * len(cols))
                    upd = ", ".join("`%s`=VALUES(`%s`)" % (c, c) for c in cols)
                    sql = ("INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s"
                           % (tbl, col_sql, placeholders, upd))
                    data = [[rv.get(c) for c in cols] for rv in ins]
                    cur.executemany(sql, data)
                if del_pks:
                    pkcols = list(del_pks[0].keys())
                    pk_sql = " AND ".join("`%s`=%%s" % c for c in pkcols)
                    cur.executemany("DELETE FROM `%s` WHERE %s" % (tbl, pk_sql),
                                    [[pk.get(c) for c in pkcols] for pk in del_pks])
        wc.commit()
        log("webhost applied batch %d" % bid)
    except Exception as e:
        wc.rollback()
        log("webhost apply failed: %s" % e)
        _return_batch(LOCAL, bid, MAX_ATTEMPTS)
        return 1
    finally:
        wc.close()

    # --- 3. mark sent on LOCAL ---
    _mark_sent(LOCAL, bid)
    return 0


def _return_batch(local, bid, max_attempts):
    c = pymysql.connect(autocommit=False, **local)
    try:
        with c.cursor() as cur:
            cur.execute("UPDATE fa_sync_outbox SET status='error' "
                        "WHERE batch_id=%s AND attempts>=%s", (bid, max_attempts))
            cur.execute("UPDATE fa_sync_outbox SET status='pending' "
                        "WHERE batch_id=%s AND attempts<%s", (bid, max_attempts))
            c.commit()
        log("batch %d returned to pending (attempts capped at %s)" % (bid, max_attempts))
    finally:
        c.close()


def _mark_sent(local, bid):
    c = pymysql.connect(autocommit=False, **local)
    try:
        with c.cursor() as cur:
            cur.execute("UPDATE fa_sync_outbox SET status='sent' WHERE batch_id=%s", (bid,))
            c.commit()
        log("batch %d marked sent" % bid)
    finally:
        c.close()


if __name__ == "__main__":
    sys.exit(main())
