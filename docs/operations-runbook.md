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


#### Encrypted offsite private-media backup and isolated recovery

Database backups alone do **not** recover identity documents or other non-database private assets. A separate, **opt-in** media archive is implemented by \`resavar:backup-private-media\`. The command uses private storage as a **read-only source**, excludes existing DB-backup archives, encrypts file **paths and contents** into independent authenticated 256 KiB chunks, uploads them to dedicated \`resavar_offsite\` storage, publishes the signed/encrypted manifest last, then reads back/decrypts/checks **all files and hashes**. It never claims a completed backup if any chunk is missing or fails verification.

**Configuration (operators only; never commit credentials):**

- Install/configure the Laravel S3 Flysystem adapter in the deployment environment if not bundled, and provision a separate bucket with restricted write/read access, versioning and server-side encryption / object-lock retention. Do not point the offsite disk to the same local folder as private media.
- Set \`RESAVAR_OFFSITE_BACKUP_DISK=resavar_offsite\`, \`RESAVAR_OFFSITE_ACCESS_KEY_ID\`, \`RESAVAR_OFFSITE_SECRET_ACCESS_KEY\`, \`RESAVAR_OFFSITE_REGION\`, \`RESAVAR_OFFSITE_BUCKET\`; optional endpoint and root settings are \`RESAVAR_OFFSITE_ENDPOINT\` and \`RESAVAR_OFFSITE_ROOT\`. Backups are **not** enabled automatically when these are absent.
- To enable daily non-overlapping scheduled media archives at 03:10 application time, set \`RESAVAR_PRIVATE_MEDIA_BACKUP_ENABLED=true\` and reload configuration/scheduler. Ensure the offsite bucket has independently enforced retention/lifecycle policies. Missing offsite connectivity must alert; never silently fall back to the production disk.

**Commands:**

1. \`php artisan resavar:backup-private-media\` creates and fully verifies the encrypted archive; record its returned \`manifest\` and backup timestamp.
2. \`php artisan resavar:backup-private-media --manifest="resavar/private-media/<RUN_ID>/manifest.rsvenc"\` independently re-verifies every offsite object against the authenticated manifest and SHA-256 hashes.
3. Provision a **new empty local directory** whose canonical path contains \`restore\` or \`drill\`, outside and not above/below \`storage/app/private\`. Point \`RESAVAR_MEDIA_RESTORE_ROOT\` there. Do not connect outbound mail, webhooks, or public endpoints.
4. \`php artisan resavar:backup-private-media --manifest="resavar/private-media/<RUN_ID>/manifest.rsvenc" --restore-drill --confirm-isolated=RESTORE_PRIVATE_MEDIA_TO_ISOLATED_ROOT\` verifies everything first, refuses any nonempty destination (including symlinks) and checks each restored file's size and SHA-256.
5. Use \`resavar:restore-drill\` separately for the matching database snapshot. Cross-check DB identity document records and expected private paths, run read-only document retrieval under proper role permissions, and record actual recovery time/point in [operator acceptance #123](https://github.com/wbizmo/azari-residences/issues/123). Never serve the isolated copy publicly.

**Important:** Copy and protect the matching Laravel \`APP_KEY\` independently; both database and private-media ciphertext are unrecoverable without it. Key rotation needs an explicit re-encryption plan. Objects left by interrupted uploads have no published manifest; configure offsite lifecycle cleanup for orphaned chunks, while retaining valid snapshots under immutable policy. Do **not** treat a locally passing parser/CI build as a verified offsite recovery.


## Executable isolated database restore rehearsal

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


### Read-only external provider settlement reconciliation

A successful local payment/refund state is **not** proof of settlement to the merchant bank account. To compare an independently downloaded provider statement to Resavar records, normalize the provider CSV with exact columns:

```csv
type,reference,currency,amount,status
payment,PROVIDER-TX-ID,NGN,120.00,settled
refund,PROVIDER-REFUND-ID,NGN,20.00,settled
```

Here `reference` is the **provider** reference, not the Resavar merchant reference. `type` is `payment` or `refund`. `status` is `settled` or `reversed`. Amounts are positive normalized decimal major units with exactly two decimal places; do not include fees in these gross amounts. Only run this format for two-decimal currencies. **Do not upload raw provider statements, customer PII, card or identity data to GitHub.**

Place the sanitized statement on a protected local/administrative workstation and execute:

```bash
php artisan resavar:reconcile-provider-statement flutterwave /secure/normalized-statement.csv --json
```

The command is read-only. Its failure exit status or exception list calls for human reconciliation. It detects missing/ambiguous provider references, locally unverified settlements, mismatched amounts/currencies, duplicate rows and provider reversals. Summaries are grouped by currency and do not perform FX conversion. A zero-exception report means **only the rows supplied matched**; it does not prove bank payout, fees, absence of omitted rows, or chargeback finality. Compare independent provider and bank account statements and reconcile daily net fees and chargebacks under #123 before final financial signoff.



### Fresh migrated restore databases with Laravel-generated defaults

A migrated isolated database may already contain the application's default `permissions` and `site_settings` rows. By default `resavar:restore-drill` refuses **any** existing non-migration data, including these seed rows. After verifying that the target is a **separate, disposable, newly migrated database**, add the independent acknowledgement `--replace-migration-seeds=REPLACE_ONLY_MIGRATION_DEFAULTS` to permit clearing **only** those two known migration-populated tables before inserting archived records. Other tables (users, bookings, payments, refunds, ledger, guest identity records) **must** remain empty and are never allowed to be overwritten. Never enable this option for a real/active database; #123 still requires the operator's actual isolated restore evidence.
