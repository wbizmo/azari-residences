<?php
namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAddOn;
use App\Models\BookingHold;
use App\Models\BookingStatusHistory;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AzariBookingFlowController extends Controller {
    public function checkout(string $token, AzariPricingEngine $p) {
        $hold=BookingHold::query()->with('property')->active()->where('token',$token)->firstOrFail();
        return view('public.bookings.checkout',['hold'=>$hold,'quote'=>$p->quote($hold->property,$hold->check_in,$hold->check_out),'addOns'=>BookingAddOn::query()->where('is_active',true)->orderBy('sort_order')->get()]);
    }

    public function store(Request $r, AzariAvailabilityEngine $a, AzariPricingEngine $p) {
        $d=$r->validate(['hold_token'=>['required','uuid','exists:booking_holds,token'],'guest_name'=>['required','string','max:160'],'guest_email'=>['required','email:rfc','max:190'],'guest_phone'=>['nullable','string','max:40'],'guest_notes'=>['nullable','string','max:3000'],'add_ons'=>['nullable','array'],'add_ons.*'=>['nullable','integer','min:0','max:20'],'terms'=>['accepted']]);
        $hold=BookingHold::query()->with('property')->active()->where('token',$d['hold_token'])->first();
        if(!$hold) throw ValidationException::withMessages(['hold_token'=>'Reservation hold expired.']);
        if(!$a->available($hold->property_id,$hold->check_in,$hold->check_out,null,$hold->token)) throw ValidationException::withMessages(['hold_token'=>'Residence is no longer available.']);
        $quote=$p->quote($hold->property,$hold->check_in,$hold->check_out,$d['add_ons']??[]);
        $booking=DB::transaction(function() use($r,$d,$hold,$quote) {
            do{$ref='AZR-'.now()->format('ymd').'-'.Str::upper(Str::random(7));}while(Booking::query()->where('reference',$ref)->exists());
            $b=Booking::query()->create([
                'reference'=>$ref,'user_id'=>$r->user()?->getKey(),'property_id'=>$hold->property_id,'hold_token'=>$hold->token,
                'guest_name'=>$d['guest_name'],'guest_email'=>$d['guest_email'],'guest_phone'=>$d['guest_phone']??null,'guest_notes'=>$d['guest_notes']??null,
                'check_in'=>$hold->check_in,'check_out'=>$hold->check_out,'adults'=>$hold->adults,'children'=>$hold->children,'rooms'=>$hold->rooms,
                'status'=>'pending','verification_status'=>'unverified','currency'=>$quote['currency'],'nightly_rate'=>$quote['nightly_rate'],'nights'=>$quote['nights'],
                'subtotal'=>$quote['subtotal'],'fee_total'=>$quote['fee_total'],'add_on_total'=>$quote['add_on_total'],'tax_rate'=>$quote['tax_rate'],
                'tax_total'=>$quote['tax_total'],'total'=>$quote['total'],'pricing_snapshot'=>$quote,'expires_at'=>now()->addHours(24),
            ]);
            foreach($quote['add_ons'] as $x) DB::table('booking_add_on_booking')->insert(['booking_id'=>$b->id,'booking_add_on_id'=>$x['id'],'quantity'=>$x['quantity'],'unit_price'=>$x['unit_price'],'line_total'=>$x['line_total'],'created_at'=>now(),'updated_at'=>now()]);
            BookingStatusHistory::query()->create(['booking_id'=>$b->id,'changed_by'=>$r->user()?->getKey(),'from_status'=>null,'to_status'=>'pending','note'=>'Booking created.','metadata'=>['channel'=>$r->user()?'registered':'guest']]);
            $hold->delete(); return $b;
        },3);
        return redirect()->route('azari.booking.summary',$booking->reference)->with('success','Booking created.');
    }

    public function summary(string $reference) {
        $booking=Booking::query()->with('property')->where('reference',$reference)->firstOrFail();
        return view('public.bookings.summary',compact('booking'));
    }
}
