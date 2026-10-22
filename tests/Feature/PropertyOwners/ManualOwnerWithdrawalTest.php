<?php

namespace Tests\Feature\PropertyOwners;

use App\Models\OwnerLedgerEntry;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Owners\OwnerBalanceService;
use App\Services\Owners\OwnerWithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ManualOwnerWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCredit(float $amount = 250): User
    {
        $owner = User::factory()->create();
        OwnerLedgerEntry::query()->create([
            'user_id' => $owner->id, 'type' => 'booking_earning',
            'direction' => 'credit', 'amount' => $amount, 'currency' => 'USD',
            'reference' => 'EARN-'.Str::upper(Str::random(16)),
            'description' => 'Test posted earning',
        ]);
        return $owner;
    }

    public function test_request_reserves_funds_and_encrypts_bank_instructions(): void
    {
        $owner = $this->ownerWithCredit();
        $token = (string) Str::uuid();
        $service = app(OwnerWithdrawalService::class);
        $w = $service->requestManual($owner, 'USD', '75.00', 'Example Bank, Owner Name, account 1234567890', null, $token);
        $this->assertSame('manual', $w->gateway);
        $this->assertSame('pending', $w->status);
        $this->assertSame(175.0, app(OwnerBalanceService::class)->available($owner, 'USD'));
        $raw = DB::table('withdrawal_requests')->where('id', $w->id)->value('receiving_details');
        $this->assertStringNotContainsString('1234567890', $raw);
        $this->assertSame('Example Bank, Owner Name, account 1234567890', $w->fresh()->receiving_details);
        $again = $service->requestManual($owner, 'USD', '75.00', 'Example Bank, Owner Name, account 1234567890', null, $token);
        $this->assertSame($w->id, $again->id);
        $this->assertSame(1, WithdrawalRequest::query()->count());
    }

    public function test_approve_and_mark_paid_debits_exactly_once_by_independent_staff(): void
    {
        $owner = $this->ownerWithCredit();
        $staff1 = User::factory()->create();
        $staff2 = User::factory()->create();
        $service = app(OwnerWithdrawalService::class);
        $w = $service->requestManual($owner, 'USD', '100.00', 'Example Bank, Owner Name, account 9876543210');
        $service->approveManual($w, $staff1, 'Bank details and owner verified.');
        $this->assertSame(150.0, app(OwnerBalanceService::class)->available($owner, 'USD'));
        try {
            $service->markManualPaid($w, $staff1, 'BANK-TEST-A', 'Independent transfer confirmation');
            $this->fail('Self-confirmation must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different finance reviewer', $e->getMessage());
        }
        $paid = $service->markManualPaid($w, $staff2, 'BANK-TEST-A', 'Bank statement confirms transfer');
        $this->assertSame('processed', $paid->status);
        $this->assertSame(150.0, app(OwnerBalanceService::class)->available($owner, 'USD'));
        $this->assertSame(1, OwnerLedgerEntry::query()->where('withdrawal_request_id', $w->id)->count());
        $this->expectException(RuntimeException::class);
        $service->markManualPaid($w, $staff2, 'BANK-TEST-A', 'Second duplicate attempt');
    }

    public function test_rejected_manual_request_releases_reserved_funds(): void
    {
        $owner = $this->ownerWithCredit();
        $service = app(OwnerWithdrawalService::class);
        $w = $service->requestManual($owner, 'USD', '85.00', 'Bank name and account number 1234567890');
        $this->assertSame(165.0, app(OwnerBalanceService::class)->available($owner, 'USD'));
        $service->rejectManual($w, User::factory()->create(), 'Invalid bank evidence provided.');
        $this->assertSame(250.0, app(OwnerBalanceService::class)->available($owner, 'USD'));
        $this->assertSame(0, OwnerLedgerEntry::query()->where('withdrawal_request_id', $w->id)->count());
    }

    public function test_currency_and_balance_cannot_be_forged(): void
    {
        $owner = $this->ownerWithCredit();
        $service = app(OwnerWithdrawalService::class);
        foreach ([['EUR', '10.00'], ['USD', '250.01'], ['USD', '1.001']] as [$currency, $amount]) {
            try {
                $service->requestManual($owner, $currency, $amount, 'Bank name and account number 1234567890');
                $this->fail('Unsafe withdrawal amount/currency must be blocked');
            } catch (\Illuminate\Validation\ValidationException|RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertSame(0, WithdrawalRequest::query()->count());
    }
}
