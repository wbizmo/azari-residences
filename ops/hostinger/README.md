# Resavar Hostinger runtime wrappers

Hostinger's hPanel PHP cron runner invokes account scripts with `/usr/bin/php`.
On this account that binary is PHP 8.2, while Resavar requires PHP 8.4.

The PHP 8.2 CLI disables `passthru`, `exec`, `system`, and
`shell_exec`, but `proc_open` is available. These wrappers therefore launch
Laravel with:

`/opt/alt/php84/usr/bin/php`

Production copies live outside `public_html`:

- `/home/u284727446/resavar-scheduler-cron.php`
- `/home/u284727446/resavar-queue-cron.php`

Hostinger hPanel cron suffixes:

- `resavar-scheduler-cron.php`
- `resavar-queue-cron.php`

Both run every minute.

The wrappers use non-blocking `flock()` locks so overlapping invocations are
suppressed safely.

Expected readiness after both jobs fire:

`{"status":"ready","checks":{"database":true,"cache":true,"storage":true,"scheduler":true,"queue":true}}`
