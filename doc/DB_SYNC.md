# KSF DB Sync — local MariaDB → webhost clone / offsite backup

## Why this exists

Kevin's latency finding: a web app co-located with its DB (localhost) is
orders of magnitude faster than an app-on-LAN talking to a DB-over-WAN. The fix
is **co-location**, not faster WAN. So we run two co-located pairs:

- **LAN pair:** FA web app + local MariaDB (in this pod). Fast for local work.
- **Webhost pair:** FA web app + CPanel MariaDB (offsite). Fast for prod/users.

The `db-sync` container keeps the two clones consistent **asynchronously** so
neither app ever serves queries across the WAN. It also satisfies the offsite
backup need (the webhost copy is the machine-independent backup that saved us
when the LAN DB drive died).

## Two mechanisms (both push local → webhost)

### Option A — nightly full dump (`nightly_dump.py`, 03:00)
- `mysqldump --single-transaction` of the local FA DB, piped straight into the
  webhost DB (`mysql`).
- Excludes the `fa_sync_outbox` table and **all triggers**, so the webhost
  stays a pure *data* clone (no recursive capture there).
- Also writes a rotating local copy under `/backups` (named volume, 7-day
  retention) — **not** the git repo.
- Acts as a clean full refresh: self-heals any drift the delta sync may leave.

### Option B — intraday outbox delta (`setup_outbox.py` + `sync_outbox.py`, every 5 min)
- `setup_outbox.py` (idempotent, runs at container start) creates the
  `fa_sync_outbox` table and, for every FA table, three triggers
  (`AI`/`AU`/`AD`) that write each changed row as JSON into the outbox.
- `sync_outbox.py` claims a batch of pending rows, replays them to the webhost
  as idempotent upserts/deletes by primary key, then marks the batch sent.
- Resumable and crash-safe (see transaction note below).

## Transaction design (correction to the naive plan)

The original sketch was: *start transaction → read outbox → build inserts →
transmit to webhost → on success update outbox → end transaction.*

That can't work as written: **a single transaction cannot span two database
servers**, and holding locks on the local outbox during a WAN round-trip would be
a long-lived transaction (bad for concurrency). The implemented design separates
the two:

1. **Claim (local, short transaction):** `SELECT … WHERE status='pending' … FOR
   UPDATE SKIP LOCKED` → `UPDATE … SET status='inflight', batch_id=?`. Commit.
   This is the only local transaction, and it's brief.
2. **Transmit (webhost, its own transaction):** build one `INSERT … ON DUPLICATE
   KEY UPDATE` per table (the "one insert per table" you described) plus
   `DELETE`s for removals; run on the webhost connection and commit there.
3. **Acknowledge (local):** `UPDATE … SET status='sent' WHERE batch_id=?`.

On any webhost failure the batch is returned to `pending` (capped at
`SYNC_MAX_ATTEMPTS`, then `error`) so the next run retries. Because every webhost
write is an upsert by PK, re-sending is safe. This is exactly why this pattern
doesn't suffer the "replication stopped, nightmare to restart" problem native
replicas have.

## Outbox schema

```
fa_sync_outbox(
  id BIGINT PK AUTO_INCREMENT,
  tbl VARCHAR(128),
  op ENUM('I','U','D'),
  row_json JSON,          -- full changed row (for I/U)
  pk_json  JSON,          -- primary key (for D)
  status ENUM('pending','inflight','sent','error') DEFAULT 'pending',
  batch_id INT,
  attempts TINYINT,
  error TEXT,
  created_at TIMESTAMP
)
```

Sent rows older than 7 days are purged each run to keep the table small.

## Operations

- **Bootstrap a fresh webhost:** let Option A run once (it does a full load), or
  run `nightly_dump.py` manually. Option B needs no special bootstrap.
- **Limit which tables sync:** set `SYNC_TABLES=tbl1,tbl2,…` (comma list) in the
  container env to restrict trigger creation to an allowlist. Empty = all user
  tables (except the denylist: `fa_sync_outbox`, `information_schema`, `mysql`,
  `performance_schema`, `sys`).
- **Force trigger rebuild:** `podman exec ksf-db-sync python3
  /usr/local/bin/setup_outbox.py` (idempotent — drops/recreates triggers).
- **Rotate / inspect:** local dumps live in the `…_backups` volume
  (`/backups/ksf_fa_YYYYMMDD.sql`); sync activity in `/backups/sync.log`.
- **Credentials:** passed via the pod's environment from `podman/.env`
  (gitignored) / Ansible Vault. `nightly_dump.py` passes DB passwords through
  `MYSQL_PWD` (environment), never on the command line.

## Security notes

- The webhost MariaDB must allow the sync container's egress IP (CPanel Remote
  MySQL access list). If the workload moves hosts, update that allowlist.
- Prefer a dedicated, least-privilege webhost DB user (INSERT/UPDATE/DELETE on
  the FA tables only) and, if the host supports it, require TLS for the WAN
  connection.
- The webhost clone is **not** a source of truth and must never be written to by
  anything other than this sync (otherwise deltas will conflict).

## Git hygiene (why dumps no longer bloat the repo)

Previously the deploy playbook wrote `backup/ksf_fa.sql` **inside the repo**, and
because that file was committed *before* `.gitignore` covered it, it stayed
tracked and rewrote itself on every deploy (git stores full snapshots, not CVS
diffs — hence the disk blow-up). Fix: `git rm --cached backup/ksf_fa.sql`
(untrack; `.gitignore` already ignores `backup/*.sql`) and move nightly dumps to
the `…_backups` volume. Likewise `podman/.env` (real credentials) is untracked so
the committed template stays `podman/.env.example` only.

## Files

- `containerfiles/sync/Podfile` — image (alpine + mariadb-client + python + dcron)
- `containerfiles/sync/setup_outbox.py` — outbox table + CDC triggers
- `containerfiles/sync/sync_outbox.py` — Option B replay worker
- `containerfiles/sync/nightly_dump.py` — Option A full dump → webhost
- `containerfiles/sync/crontab` — schedule (`*/5` delta, `0 3` full)
- `containerfiles/sync/entrypoint.sh` — setup then `dcron -f`
- `podman/ksf-compose.yaml` — `db-sync` service + `…_backups` volume
- `podman/.env.example` — `WEBHOST_*` + sync tuning vars
