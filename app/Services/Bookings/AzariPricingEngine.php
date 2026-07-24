<?php
namespace App\Services\Bookings;

use App\Models\BookingAddOn;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AzariPricingEngine {
    public function quote(Property $property, CarbonInterface $in, CarbonInterface $out, array $selected=[]): array {
        $cursor=CarbonImmutable::parse($in); $end=CarbonImmutable::parse($out); $breakdown=[];
        while($cursor->lt($end)) {
            $rate=$this->rate($property,$cursor);
            $breakdown[]=['date'=>$cursor->toDateString(),'rate'=>round($rate,2)];
            $cursor=$cursor->addDay();
        }
        $nights=count($breakdown); $subtotal=round(array_sum(array_column($breakdown,'rate')),2);
        $fee=round((float)($property->service_fee ?? config('azari.booking.default_service_fee',0)),2);
        $addons=[]; $addonTotal=0;
        foreach(BookingAddOn::query()->whereIn('id',array_keys($selected))->where('is_active',true)->get() as $a) {
            $qty=max(0,(int)($selected[$a->id] ?? 0)); if(!$qty) continue;
            $mult=match($a->pricing_type){'per_night'=>max(1,$nights),'per_quantity_per_night'=>max(1,$nights)*$qty,default=>$qty};
            $line=round((float)$a->price*$mult,2); $addonTotal+=$line;
            $addons[]=['id'=>$a->id,'name'=>$a->name,'quantity'=>$qty,'unit_price'=>(float)$a->price,'line_total'=>$line];
        }
        $taxRate=(float)($property->tax_rate ?? config('azari.booking.default_tax_rate',0));
        $tax=round(($subtotal+$fee+$addonTotal)*($taxRate/100),2);
        return [
            'currency'=>$property->currency ?? 'NGN','nights'=>$nights,
            'nightly_rate'=>$nights ? round($subtotal/$nights,2) : 0,'nightly_breakdown'=>$breakdown,
            'subtotal'=>$subtotal,'fee_total'=>$fee,'add_ons'=>$addons,'add_on_total'=>$addonTotal,
            'tax_rate'=>$taxRate,'tax_total'=>$tax,'total'=>round($subtotal+$fee+$addonTotal+$tax,2),
        ];
    }

    private function rate(Property $property, CarbonInterface $date): float {
        if (DB::getSchemaBuilder()->hasTable('seasonal_prices')) {
            $s=DB::table('seasonal_prices')->where('property_id',$property->getKey())
                ->whereDate('starts_on','<=',$date)->whereDate('ends_on','>=',$date)->orderByDesc('starts_on')->first();
            if($s) return (float)$s->nightly_rate;
        }
        if(in_array($date->dayOfWeekIso,[6,7],true) && (float)($property->weekend_rate ?? 0)>0) return (float)$property->weekend_rate;
        return (float)($property->nightly_rate ?? 0);
    }
}
