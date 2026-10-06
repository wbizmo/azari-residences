<?php

$app = "/home/u284727446/domains/resavar.com/public_html";
$php = "/opt/alt/php84/usr/bin/php";
$log = "/home/u284727446/queue.log";
$lockPath = "/home/u284727446/.resavar-queue.lock";

$lock = fopen($lockPath, "c");

if ($lock === false) {
    exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    exit(0);
}

$command = [
    $php,
    $app . "/artisan",
    "queue:work",
    "database",
    "--stop-when-empty",
    "--tries=3",
    "--timeout=90",
];

$descriptors = [
    0 => ["file", "/dev/null", "r"],
    1 => ["file", $log, "a"],
    2 => ["file", $log, "a"],
];

$process = proc_open($command, $descriptors, $pipes, $app);

if (!is_resource($process)) {
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

$status = proc_close($process);

flock($lock, LOCK_UN);
fclose($lock);

exit($status);
