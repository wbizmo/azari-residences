<?php
namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AzariBookingLifecycle {
    private const MAP=[
        'hold'=>['pending','cancelled','expired'],'pending'=>['approved','cancelled','expired'],
        'approved'=>['confirmed','cancelled'],'confirmed'=>['checked_in','cancelled','no_show'],
        'checked_in'=>['checked_out'],'checked_out'=>[],'cancelled'=>[],'expired'=>[],'no_show'=>[]
    ];

    public function transition(Booking $booking,string $to,?int $actor=null,?string $note=null): Booking {
        $from=$booking->status;
        if(!in_array($to,self::MAP[$from] ?? [],true)) throw ValidationException::withMessages(['status'=>"Cannot move from {$from} to {$to}."]);
        return DB::transaction(function() use($booking,$from,$to,$actor,$note) {
            $u=['status'=>$to];
            if($to==='approved') $u['approved_at']=now();
            if($to==='cancelled') $u['cancelled_at']=now();
            if(in_array($to,['approved','confirmed','checked_in'],true)) $u['room_assignment_locked_at']=now();
            if(in_array($to,['confirmed','checked_in','checked_out'],true)) $u['payment_transfer_locked_at']=now();
            $booking->forceFill($u)->save();
            BookingStatusHistory::query()->create(['booking_id'=>$booking->getKey(),'changed_by'=>$actor,'from_status'=>$from,'to_status'=>$to,'note'=>$note]);
            return $booking->refresh();
        },3);
    }

    public function assertModifiable(Booking $b): void {
        if(!in_array($b->status,config('azari.booking.modifiable_statuses'),true)) throw ValidationException::withMessages(['booking'=>'Booking can no longer be modified.']);
    }
    public function assertRoomTransfer(Booking $b): void {
        if($b->room_assignment_locked_at) throw ValidationException::withMessages(['property_id'=>'Residence assignment is locked.']);
    }
    public function assertPaymentTransfer(Booking $b): void {
        if($b->payment_transfer_locked_at) throw ValidationException::withMessages(['payment'=>'Payment transfer is locked.']);
    }
}
