<?php

namespace App\Services\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait Transactional
{
    protected function transaction(Closure $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }

    protected function supportsRowLocks(): bool
    {
        return DB::connection()->getDriverName() !== 'sqlite';
    }

    protected function applyLock(Builder $query): Builder
    {
        if ($this->supportsRowLocks()) {
            $query->lockForUpdate();
        }

        return $query;
    }

    protected function lockModel(Model $model): Model
    {
        return $this->applyLock(
            $model->newQuery()->whereKey($model->getKey())
        )->firstOrFail();
    }
}
