# Resarva production operations runbook

## Release gate

Run `php artisan azari:release-gate` after dependencies, migrations and the frontend build are present. GitHub Actions uses `azari:release-gate --ci`; PHPUnit/Laravel tests and Playwright remain local-only and are not part of CI.

Before promotion, verify queue workers and the Laravel scheduler are healthy, payment/messaging provider configuration is present, `public/build/manifest.json` exists, sitemap and robots files are current, and a verified backup exists.

## Scheduler and queue health

The scheduler runs `azari:heartbeat` every minute. It records a scheduler heartbeat and dispatches a queue heartbeat job. `/health/live` proves the web process is alive. `/health/ready` performs sanitized database/cache/storage checks and checks recent scheduler/queue heartbeats without exposing provider names, hostnames or credentials.

If the queue heartbeat is stale, inspect worker supervision and failed jobs before accepting new production load. If the scheduler heartbeat is stale, confirm the system cron invokes `php artisan schedule:run` every minute.

## Backup and restore drill

Create and verify a backup with `php artisan azari:backup --verify`. Run `php artisan azari:backup-restore-check` to verify checksum, gzip decompression and structural integrity without changing production data.

For a real restore, never restore over a live production database. Provision an isolated database from the same schema version, stop application writes, decrypt/copy the backup through the approved secret-handling path, verify its checksum, restore tables in dependency order, run booking/payment/inventory reconciliation, run the full local Laravel and Playwright suites against the isolated restore, then perform an explicit controlled cutover. Preserve the original production database until reconciliation and rollback windows have expired.

## Incident correlation

Every HTTP response carries `X-Request-ID`. Application logs include the same request ID. Audit events retain the request ID and critical booking/payment operations retain their booking/payment references. Do not log API keys, authorization headers, identity documents, full webhook payloads or secrets.

## External channel incidents

Channel imports are idempotent by connection + external reservation ID. A stale or failed connection configured `fail_closed` removes its linked inventory from sale until a successful sync. Resolve feed/network/provider failures, trigger a new sync, and verify `last_successful_sync_at` before reopening inventory.
