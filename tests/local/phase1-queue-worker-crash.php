<?php
/**
 * LOCAL ONLY: deliberate database-queue worker crash/retry rehearsal.
 *
 * Requires:
 *   APP_ENV=testing
 *   QUEUE_CONNECTION=database DB_QUEUE_RETRY_AFTER=4
 *   disposable migrated *test*, *sandbox* or *isolat* file/MySQL database
 *   RESAVAR_TEST_PHP=/path/to/php (optional, for custom PHP module wrappers)
 *
 * php tests/local/phase1-queue-worker-crash.php
 */
declare(strict_types=1);

use App\Jobs\PhaseOneLocalQueueRecoveryProbe;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = (string) DB::connection()->getDatabaseName();
$driver = (string) DB::connection()->getDriverName();
if (! app()->environment('testing')
    || ! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)
    || $db === ':memory:'
    || ! preg_match('/(?:test|sandbox|isolat)/i', $db)
    || (string) config('queue.default') !== 'database'
    || (int) config('queue.connections.database.retry_after') !== 4
    || ! function_exists('posix_kill')) {
    fwrite(STDERR, "Refused: testing mode, disposable DB, database queue, retry_after=4 and POSIX required.\n");
    exit(2);
}

$token = bin2hex(random_bytes(16));
$signal = sys_get_temp_dir().'/rsv-queue-recovery-'.$token;
$component = 'phase1-probe-'.$token;
$artisan = dirname(__DIR__, 2).'/artisan';
$php = (string) (getenv('RESAVAR_TEST_PHP') ?: PHP_BINARY);
$workerArgs = [
    $php, $artisan, 'queue:work', 'database',
    '--once', '--tries=4', '--timeout=25', '--sleep=1',
];

$env = [
    'APP_ENV' => 'testing',
    'QUEUE_CONNECTION' => 'database',
    'DB_QUEUE_RETRY_AFTER' => '4',
    'DB_CONNECTION' => $driver,
    'DB_DATABASE' => $db,
];
$first = new Process($workerArgs, dirname(__DIR__, 2), $env, null, 30);
try {
    Queue::connection('database')->push(new PhaseOneLocalQueueRecoveryProbe($token, $signal));
    $first->start();
    $deadline = microtime(true) + 12;
    while (! is_file($signal) && microtime(true) < $deadline && $first->isRunning()) {
        usleep(25000);
    }
    if (! is_file($signal) || ! $first->isRunning()) {
        throw new RuntimeException('First worker did not reach the committed-before-ACK point: '.$first->getErrorOutput());
    }
    // Kill ONLY the Symfony process started above, never a supervisor,
    // cron entry, system-wide queue worker or production process.
    $first->signal(SIGKILL);
    $first->wait();

    $before = DB::table('system_heartbeats')->where('component', $component)->count();
    if ($before !== 1) {
        throw new RuntimeException('Synthetic effect was not persisted before the worker was killed.');
    }
    sleep(6); // Past configured retry_after=4; job becomes visible again.

    $second = new Process($workerArgs, dirname(__DIR__, 2), $env, null, 30);
    $second->mustRun();
    $after = DB::table('system_heartbeats')->where('component', $component)->count();
    $queued = DB::table('jobs')->where('payload', 'like', '%'.$token.'%')->count();

    $passed = $before === 1 && $after === 1 && $queued === 0;
    echo json_encode([
        'environment' => 'testing',
        'driver' => $driver,
        'retry_after_seconds' => 4,
        'worker_was_killed_before_ack' => true,
        'idempotent_effect_before_retry' => $before,
        'idempotent_effect_after_retry' => $after,
        'unacked_matching_jobs_after_recovery' => $queued,
        'passed' => $passed,
        'note' => 'Synthetic idempotent DB effect only, not proof of remote provider exactly-once semantics.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    exit($passed ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Local queue recovery failed: '.$e::class."\n");
    exit(1);
} finally {
    if ($first->isRunning()) {
        $first->stop(0, SIGKILL);
    }
    @unlink($signal);
}
