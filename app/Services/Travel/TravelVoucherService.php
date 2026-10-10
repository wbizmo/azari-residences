<?php

namespace App\Services\Travel;

use App\Models\TravelFulfillment;
use App\Models\TravelVoucher;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An experience voucher is not an inventory hold. It can exist only after a
 * separately verified payment AND an authenticated supplier confirmation.
 * Redemption is atomic and cannot modify accommodation/payment records.
 */
final class TravelVoucherService
{
    public function issue(TravelFulfillment $fulfillment): TravelVoucher
    {
        return DB::transaction(function () use ($fulfillment): TravelVoucher {
            $locked = TravelFulfillment::query()->whereKey($fulfillment->id)
                ->lockForUpdate()->firstOrFail();
            $travel = $locked->travelRequest;
            if ($travel->kind !== 'experience'
                || $locked->status !== 'confirmed'
                || $locked->confirmed_at === null
                || $locked->provider_confirmed_at === null
                || $locked->payment_verified_at === null
                || $locked->amount_minor !== $travel->quoted_total_minor
                || $locked->currency !== $travel->currency) {
                throw ValidationException::withMessages([
                    'fulfillment' => 'An authenticated supplier confirmation and verified activity payment are required.',
                ]);
            }
            if ($existing = TravelVoucher::query()->where('travel_fulfillment_id', $locked->id)->first()) {
                return $existing;
            }

            $token = 'RVX-'.strtoupper(bin2hex(random_bytes(20)));
            return TravelVoucher::query()->create([
                'travel_fulfillment_id' => $locked->id,
                'token_hash' => hash('sha256', $token),
                'token_encrypted' => Crypt::encryptString($token),
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        }, 3);
    }

    public function displayToken(TravelVoucher $voucher, User $guest): string
    {
        $voucher->loadMissing('fulfillment.travelRequest');
        abort_unless((int) $voucher->fulfillment->travelRequest->user_id === (int) $guest->id, 404);
        abort_unless($voucher->status === 'issued'
            && $voucher->fulfillment->status === 'confirmed', 404);

        return Crypt::decryptString($voucher->token_encrypted);
    }

    public function redeem(User $staff, string $token, int $supplierId): TravelVoucher
    {
        if (! $staff->isAdministrator()) {
            abort(403);
        }
        if (! preg_match('/^RVX-[A-F0-9]{40}$/D', $token)) {
            throw ValidationException::withMessages(['voucher' => 'Invalid voucher format.']);
        }

        return DB::transaction(function () use ($staff, $token, $supplierId): TravelVoucher {
            $voucher = TravelVoucher::query()->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->first();
            if (! $voucher) {
                throw ValidationException::withMessages(['voucher' => 'Voucher not found.']);
            }
            $fulfillment = TravelFulfillment::query()->whereKey($voucher->travel_fulfillment_id)
                ->lockForUpdate()->firstOrFail();
            if ((int) $fulfillment->travel_supplier_id !== $supplierId
                || $fulfillment->status !== 'confirmed'
                || $fulfillment->travelRequest->kind !== 'experience') {
                throw ValidationException::withMessages(['voucher' => 'Voucher does not belong to this active supplier.']);
            }
            if ($voucher->status !== 'issued' || $voucher->redeemed_at !== null) {
                throw ValidationException::withMessages(['voucher' => 'Voucher was already redeemed or revoked.']);
            }
            $voucher->update([
                'status' => 'redeemed',
                'redeemed_at' => now(),
                'redeemed_by' => $staff->id,
            ]);
            return $voucher;
        }, 3);
    }

    public function revoke(TravelFulfillment $fulfillment): void
    {
        DB::transaction(function () use ($fulfillment): void {
            $locked = TravelFulfillment::query()->whereKey($fulfillment->id)
                ->lockForUpdate()->firstOrFail();
            $voucher = TravelVoucher::query()->where('travel_fulfillment_id', $locked->id)
                ->lockForUpdate()->first();
            if ($voucher && $voucher->status === 'issued') {
                $voucher->update(['status' => 'revoked']);
            }
        }, 3);
    }
}
