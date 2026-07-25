# Azari Production Deployment and Handover

1. Deploy with PHP 8.3+, required extensions, Composer, Node 20, MySQL/MariaDB, HTTPS and a writable `storage` directory.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `AZARI_TIMEZONE=Africa/Lagos`, a durable queue connection and a non-log mail transport.
3. Run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan db:seed --class=Database\\Seeders\\AzariFinalCompletionSeeder --force`, `npm ci --ignore-scripts`, and `npm run build`.
4. Run a queue worker under Supervisor or systemd. Recommended command: `php artisan queue:work --sleep=3 --tries=3 --backoff=60 --timeout=120`.
5. Run Laravel scheduler every minute: `* * * * * cd /path/to/azari && php artisan schedule:run >> /dev/null 2>&1`.
6. Configure private storage outside the public web root. Never symlink identity or support attachment directories.
7. Configure provider callback and webhook URLs over HTTPS. Verify signatures, references, amounts and currencies before confirming bookings.
8. Run `php artisan azari:backup --verify` and perform a controlled restore test before launch.
9. Run `php artisan azari:production-audit --strict`. Resolve every failure before traffic is accepted.
10. Cache production configuration only after environment values are correct: `php artisan optimize`.

## Recovery

Stop workers, place the app in maintenance mode, preserve current data, verify the selected backup checksum, restore to a separate database first, run integrity checks, switch only after validation, restart workers and record the recovery in the audit log.

## Handover

Provide administrators with role/permission guidance, provider-status interpretation, report/export usage, support escalation, backup verification, queue troubleshooting, scheduler troubleshooting and credential-rotation ownership. Do not provide secrets in the handover document.
