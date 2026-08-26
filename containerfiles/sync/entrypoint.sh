#!/bin/sh
set -e

# Idempotent: creates the outbox table + per-table change-capture triggers on the
# LOCAL MariaDB. Safe to run on every container start; only touches tables that
# already exist, so re-running after a fresh FA install picks up new tables.
python3 /usr/local/bin/setup_outbox.py || echo "setup_outbox failed (will retry next start)"

# Run the schedule daemon as PID 1 (foreground).
exec crond -f
