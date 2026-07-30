<?php

namespace App\Observers\Concerns;

use Illuminate\Support\Facades\DB;
use Throwable;

trait DispatchesAfterCommit
{
    protected function afterCommit(callable $callback): void
    {
        $safe = static function () use ($callback): void {
            try {
                $callback();
            } catch (Throwable $exception) {
                report($exception);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($safe);
            return;
        }

        $safe();
    }
}
