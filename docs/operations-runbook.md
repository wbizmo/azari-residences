# Resarva production operations runbook

## Release gate

Run `php artisan azari:release-gate` after dependencies, migrations and the frontend build are present. GitHub Actions uses `azari:release-gate --ci`; PHPUnit/Laravel tests and Playwright remain local-only and are not part of CI.

Before promotion, verify queue workers and the Laravel scheduler are healthy, payment/messaging provider configuration is present, `public/build/manifest.json` exists, sitemap and robots files are current, and a verified backup exists.

## Scheduler and queue health

The scheduler runs `azari:heartbeat` every minute. It records a scheduler heartbeat and dispatches a queue heartbeat job. `/health/live` proves the web process is alive. `/health/ready` performs sanitized database/cache/storage checks and checks recent scheduler/queue heartbeats without exposing provider names, hostnames or credentials.

If the queue heartbeat is stale, inspect worker supervision and failed jobs before accepting new production load. If the scheduler heartbeat is stale, confirm the system cron invokes `php artisan schedule:run` every minute.

## Safe failed-job replay

On **System health → Failed jobs**, administrators with system-health.manage may requeue only explicitly allowlisted, repeat-safe jobs (queue heartbeat, channel sync and managed image derivatives). The retry endpoint is CSRF protected, throttled, single-flight per failed job and audited; it never dumps failed-job payloads or exception bodies to the page. Failed bookings, refunds, provider transfers or other unapproved job types must be investigated and reconciled rather than manually replayed.

Before using the control, confirm the original failure cause is resolved. Recheck job state, channel sellability and side effects afterwards. A successful queue submission is **not** evidence the job has completed.

For a production deployment, configure a shared atomic cache-lock backend across all workers (database or Redis) and a durable queue connection; \`sync\` and \`null\` queue drivers are inappropriate for production. Calendar imports use a single-flight 120-second lock and delay missing-event cancellation by 30 minutes unless the operator explicitly allows a reviewed empty snapshot.

## Repeatable search metrics

Use \`php artisan resavar:benchmark-search --runs=50 --json\` in a controlled, isolated environment, not against live traffic. The command emits p50, p95 and p99 search latency, query distribution and PHP peak memory. Record hardware, MySQL version/isolation, dataset size, cache state, query plans and concurrency separately. It is read-only and **not** a checkout load test or a production SLA.

## Backup and restore drill

Create and verify a backup with `php artisan azari:backup --verify`. Run `php artisan azari:backup-restore-check` to verify checksum, gzip decompression and structural integrity without changing production data.

For a real restore, never restore over a live production database. Provision an isolated database from the same schema version, stop application writes, decrypt/copy the backup through the approved secret-handling path, verify its checksum, restore tables in dependency order, run booking/payment/inventory reconciliation, run the full local Laravel and Playwright suites against the isolated restore, then perform an explicit controlled cutover. Preserve the original production database until reconciliation and rollback windows have expired.


### Executable isolated database restore rehearsal

The database archive is **not** a replacement for the private-media archive. This command never restores into the live application connection. It requires a separate database, matching migrations, and explicit confirmation.

1. Provision an **empty/disposable** MySQL database whose name visibly contains \`restore\` or \`drill\` and whose schema has been migrated with the **same application commit**. Never use the production database or a database containing user data.
2. Supply \`RESAVAR_RESTORE_DB_HOST\`, \`RESAVAR_RESTORE_DB_PORT\`, \`RESAVAR_RESTORE_DB_DATABASE\`, \`RESAVAR_RESTORE_DB_USERNAME\`, and \`RESAVAR_RESTORE_DB_PASSWORD\` in the supervised operator environment. This connection does not inherit production credentials/database names.
3. Run \`php artisan migrate --database=resavar_restore --force\` against the dedicated target **only after verifying its connection configuration**.
4. Run \`php artisan azari:backup --verify\` on the source, record the verified backup ID, and run \`php artisan resavar:restore-drill BACKUP_ID --target=resavar_restore --confirm-isolated=RESTORE_TO_ISOLATED_DATABASE\`.
5. The drill rejects an unverified archive, same database, mismatched schema, populated user tables and already-restored targets. It verifies encrypted records before any target write, restores inside a transaction, checks **every** restored table's row counts, and records an audit event (connection alias, count, elapsed duration, not credentials).
6. Run read-only inventory/booking/payment and role-boundary tests on the restored target. Discard/reprovision the target after the exercise; never point public traffic or workers at it. Perform a separate encrypted **private-media** and **offsite** recovery check, then record recovery point and elapsed recovery time.
7. Never run this on production without an independently provisioned target. Restoring sensitive database rows also means granting restricted access, disabling outward email/webhooks/workers on the restore environment, and using secret-safe logging.

The command is intentionally fail-closed and is NOT a release-complete RPO/RTO claim until the actual isolated drill is performed and its evidence recorded.

## Incident correlation

Every HTTP response carries `X-Request-ID`. Application logs include the same request ID. Audit events retain the request ID and critical booking/payment operations retain their booking/payment references. Do not log API keys, authorization headers, identity documents, full webhook payloads or secrets.

## External channel incidents

Channel imports are idempotent by connection + external reservation ID. A stale or failed connection configured `fail_closed` removes its linked inventory from sale until a successful sync. Resolve feed/network/provider failures, trigger a new sync, and verify `last_successful_sync_at` before reopening inventory.
