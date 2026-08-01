<?php

namespace App\Services\Bookings;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function apply(Booking $booking, string $rawCode): Booking
    {
        return DB::transaction(function () use ($booking, $rawCode): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status, ['pending', 'pending_payment'], true) || $booking->isPaid() || $booking->isCancelled()) {
                throw ValidationException::withMessages(['voucher_code' => 'A voucher cannot be changed for this booking.']);
            }
            $voucher = Voucher::query()->where('code', strtoupper(trim($rawCode)))->lockForUpdate()->first();
            if (! $voucher || ! $voucher->is_active) throw ValidationException::withMessages(['voucher_code'=>'This voucher is invalid or inactive.']);
            if ($voucher->starts_at && $voucher->starts_at->isFuture()) throw ValidationException::withMessages(['voucher_code'=>'This voucher is not active yet.']);
            if ($voucher->expires_at && $voucher->expires_at->isPast()) throw ValidationException::withMessages(['voucher_code'=>'This voucher has expired.']);

            $base = round((float)$booking->subtotal + (float)$booking->fee_total + (float)$booking->add_on_total + (float)$booking->tax_total, 2);
            if ($base + 0.009 < (float)$voucher->minimum_booking_value) throw ValidationException::withMessages(['voucher_code'=>'This booking does not meet the voucher minimum.']);
            if ($voucher->properties()->exists() && ! $voucher->properties()->whereKey($booking->property_id)->exists()) throw ValidationException::withMessages(['voucher_code'=>'This voucher does not apply to this residence.']);
            if ($voucher->total_usage_limit !== null && $voucher->redemptions()->count() >= $voucher->total_usage_limit) throw ValidationException::withMessages(['voucher_code'=>'This voucher has reached its usage limit.']);

            $customerUses = $voucher->redemptions()->where(function (Builder $query) use ($booking): void {
                $booking->user_id ? $query->where('user_id', $booking->user_id) : $query->whereRaw('LOWER(guest_email) = ?', [strtolower($booking->guest_email)]);
            })->count();
            if ($customerUses >= $voucher->per_customer_limit) throw ValidationException::withMessages(['voucher_code'=>'You have reached the usage limit for this voucher.']);

            $discount = $voucher->discount_type === 'percentage' ? $base * ((float)$voucher->discount_value / 100) : (float)$voucher->discount_value;
            if ($voucher->maximum_discount !== null) $discount = min($discount, (float)$voucher->maximum_discount);
            $discount = round(min($base, max(0, $discount)), 2);
            if ($discount <= 0) throw ValidationException::withMessages(['voucher_code'=>'This voucher does not produce a valid discount.']);

            VoucherRedemption::query()->where('booking_id', $booking->id)->delete();
            $snapshot=['code'=>$voucher->code,'name'=>$voucher->name,'discount_type'=>$voucher->discount_type,'discount_value'=>(float)$voucher->discount_value,'maximum_discount'=>$voucher->maximum_discount === null ? null : (float)$voucher->maximum_discount,'applied_at'=>now()->toIso8601String()];
            $pricing=(array)$booking->pricing_snapshot; $pricing['voucher']=$snapshot; $pricing['discount_total']=$discount; $pricing['total_before_discount']=$base; $pricing['total']=round($base-$discount,2);
            $booking->update(['voucher_id'=>$voucher->id,'voucher_code'=>$voucher->code,'discount_total'=>$discount,'voucher_snapshot'=>$snapshot,'pricing_snapshot'=>$pricing,'total'=>round($base-$discount,2)]);
            VoucherRedemption::create(['voucher_id'=>$voucher->id,'booking_id'=>$booking->id,'user_id'=>$booking->user_id,'guest_email'=>$booking->guest_email,'discount_amount'=>$discount,'redeemed_at'=>now()]);
            AuditLog::record('voucher.applied',$booking,[],['voucher_code'=>$voucher->code,'discount_total'=>$discount]);
            return $booking->refresh();
        }, 5);
    }

    public function remove(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $booking=Booking::query()->lockForUpdate()->findOrFail($booking->id);
            if (! in_array($booking->status,['pending','pending_payment'],true) || $booking->isPaid()) throw ValidationException::withMessages(['voucher_code'=>'The voucher can no longer be removed.']);
            $base=round((float)$booking->total+(float)$booking->discount_total,2);
            VoucherRedemption::where('booking_id',$booking->id)->delete();
            $pricing=(array)$booking->pricing_snapshot; unset($pricing['voucher'],$pricing['discount_total'],$pricing['total_before_discount']); $pricing['total']=$base;
            $booking->update(['voucher_id'=>null,'voucher_code'=>null,'discount_total'=>0,'voucher_snapshot'=>null,'pricing_snapshot'=>$pricing,'total'=>$base]);
            AuditLog::record('voucher.removed',$booking);
            return $booking->refresh();
        },5);
    }
}
