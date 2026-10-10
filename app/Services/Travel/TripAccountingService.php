<?php

namespace App\Services\Travel;

use App\Models\TripItinerary;
use Illuminate\Support\Facades\DB;

/**
 * All amounts in integer minor units, grouped by original currency.
 * Never invent conversion rates, treat unpaid orders as collected, or net a
 * lodging refund against a travel supplier's independent settlement.
 */
final class TripAccountingService
{
    public function snapshot(TripItinerary $itinerary): array
    {
        $stays = $itinerary->bookings()->where('user_id',$itinerary->user_id)
            ->orderBy('id')->get();
        $travels = \App\Models\TravelRequest::query()
            ->where('trip_itinerary_id',$itinerary->id)
            ->where('user_id',$itinerary->user_id)
            ->with(['offer:id,title','fulfillment:id,travel_request_id,status,verified_payment_reference,payment_verified_at,amount_minor,currency,provider_confirmation'])
            ->orderBy('id')->get();
        $dining = \App\Models\DiningRequest::query()->where('trip_itinerary_id',$itinerary->id)
            ->where('user_id',$itinerary->user_id)->with('partner:id,name')->orderBy('id')->get();

        // One grouped lookup per financial source instead of N+1 queries per
        // accommodation and travel component, even for large trip histories.
        $stayIds=$stays->pluck('id');
        $paidByStay=$stayIds->isEmpty() ? collect() : DB::table('payments')
            ->select('booking_id')->selectRaw('SUM(amount) AS total')
            ->whereIn('booking_id',$stayIds)->where('status','successful')
            ->groupBy('booking_id')->pluck('total','booking_id');
        $refundedByStay=$stayIds->isEmpty() ? collect() : DB::table('refunds')
            ->select('booking_id')->selectRaw('SUM(amount) AS total')
            ->whereIn('booking_id',$stayIds)->where('status','successful')
            ->groupBy('booking_id')->pluck('total','booking_id');
        $travelFulfillmentIds=$travels->pluck('fulfillment.id')->filter()->values();
        $refundsByTravel=$travelFulfillmentIds->isEmpty() ? collect() : DB::table('travel_financial_events')
            ->select('travel_fulfillment_id')->selectRaw('SUM(amount_minor) AS total')
            ->whereIn('travel_fulfillment_id',$travelFulfillmentIds)
            ->where('type','verified_refund')
            ->groupBy('travel_fulfillment_id')->pluck('total','travel_fulfillment_id');

        $items=[];$totals=[];
        $record = static function (string $currency,int $quoted,int $collected,int $refunded) use (&$totals): void {
            if (! isset($totals[$currency])) $totals[$currency]=['quoted_minor'=>0,'collected_minor'=>0,'refunded_minor'=>0,'net_collected_minor'=>0];
            $totals[$currency]['quoted_minor'] += $quoted;
            $totals[$currency]['collected_minor'] += $collected;
            $totals[$currency]['refunded_minor'] += $refunded;
            $totals[$currency]['net_collected_minor'] += $collected-$refunded;
        };
        foreach($stays as $stay){
            $currency=(string)$stay->currency;
            $quoted=(int)round(((float)$stay->total)*100);
            $collected=(int)round(((float)$paidByStay->get($stay->id,0))*100);
            // Capture historical refunds independently via verified refund rows.
            $refunds=(int)round(((float)$refundedByStay->get($stay->id,0))*100);
            $record($currency,$quoted,$collected,$refunds);
            $items[]=['type'=>'stay','id'=>$stay->id,'title'=>$stay->property_name_snapshot ?: 'Resavar stay',
                'status'=>$stay->status,'currency'=>$currency,'quoted_minor'=>$quoted,
                'collected_minor'=>$collected,'refunded_minor'=>$refunds,'reference'=>$stay->reference];
        }
        foreach($travels as $travel){
            $f=$travel->fulfillment;
            $collected=$f && $f->payment_verified_at ? (int)$f->amount_minor : 0;
            $refunds=$f ? (int)$refundsByTravel->get($f->id,0) : 0;
            $record($travel->currency,(int)$travel->quoted_total_minor,$collected,$refunds);
            $items[]=['type'=>$travel->kind,'id'=>$travel->id,'title'=>$travel->offer?->title ?? ucfirst($travel->kind),
                'status'=>$f?->status ?? $travel->status,'currency'=>$travel->currency,
                'quoted_minor'=>(int)$travel->quoted_total_minor,'collected_minor'=>$collected,
                'refunded_minor'=>$refunds,'reference'=>$f?->provider_confirmation];
        }
        foreach($dining as $request){
            $items[]=['type'=>'dining','id'=>$request->id,'title'=>$request->partner?->name ?? 'Dining concierge',
                'status'=>$request->status,'currency'=>null,'quoted_minor'=>0,'collected_minor'=>0,
                'refunded_minor'=>0,'reference'=>$request->provider_reference];
        }
        ksort($totals);
        return ['items'=>$items,'totals'=>$totals,'requires_fx_quote'=>count($totals)>1,
            'can_charge_bundle'=>false];
    }
}
