<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Owners\OwnerLedgerReconciliationService;
use Illuminate\Console\Command;

class AuditOwnerLedger extends Command
{
    protected $signature = 'resavar:audit-owner-ledger
        {owner : Owner user ID}
        {currency : ISO 4217 three-letter currency}
        {--json : Print one machine-readable JSON report}';

    protected $description = 'Read-only balance, refund, payout and dispute ledger exception audit.';

    public function handle(OwnerLedgerReconciliationService $auditor): int
    {
        $id = filter_var($this->argument('owner'), FILTER_VALIDATE_INT);
        $currency = strtoupper(trim((string) $this->argument('currency')));

        if (! $id || $id < 1 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            $this->error('Valid owner ID and three-letter currency are required.');
            return self::FAILURE;
        }

        $owner = User::query()->find($id);
        if (! $owner) {
            $this->error('Owner record not found.');
            return self::FAILURE;
        }

        $result = $auditor->inspect($owner, $currency);
        $this->line(json_encode($result, $this->option('json')
            ? JSON_THROW_ON_ERROR
            : JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $result['healthy'] ? self::SUCCESS : self::FAILURE;
    }
}
