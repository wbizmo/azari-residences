# Resavar deployment and provider handoff (manual only)

No production changes are authorized by this repository audit. The old multi-domain script is **disabled**, because it targeted `theazariresidence.com` and `azarihotels.com`.

1. **Before release:** identify the canonical Resavar `main` commit; verify signed-off scope, asset logo variants (navy logo on white; white logo on navy), local Laravel suite, dependency scan, frontend/print review, backup/restore drill, and legal/provider approvals. Do not enable disabled provider flags without contracted adapters.
2. **Target verification:** manually inspect Hostinger SSH fingerprint and verify `/home/u284727446/domains/resavar.com/public_html` is the actual Laravel app. Use PHP 8.4 binary `/opt/alt/php84/usr/bin/php`. Confirm configured `APP_URL=https://resavar.com`, database engine and PHP extensions, web root, writable cache/storage and existing symlinks. Never reuse Azari domains.
3. **Build/package offline:** build Vite and vendor for PHP 8.4 in the build workspace; do not upload `.env`, `.git`, `node_modules`, storage or shell credentials. Manifest-managed deploy files only. Keep verified `public/build` and application files atomic.
4. **Database preflight:** prove an offsite, restorable database backup, determine pending migrations with `artisan migrate:status`, verify current schema on staging MySQL/MariaDB and dry-run rollback. Take an immediately pre-release backup before any migration. Only the release owner may approve `artisan migrate --force`.
5. **Maintenance:** if release owner authorizes, run migrations before final cache warming; keep rollback + application and DB restore instructions. Run `artisan config:cache`, `route:cache`, `view:cache`, queues/cron and storage linkage checks. Avoid blindly reverting migrations that already transformed live bookings/money data.
6. **Smoke and rollback:** verify home, search, a guest session, admin, invoice, receipt, PDF, QR, branding, currency toggles, payment and booking lifecycle in staging and then live using test-safe accounts; inspect logs, provider callbacks and analytics. Restore only from the verified backup when schema/data rollback is proven safe.

**Project-owner handoff:** production readiness, actual provider credentials, commercial and regulatory approvals, Hostinger release authorization, and production smoke verification.
