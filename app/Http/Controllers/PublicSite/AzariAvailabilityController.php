<?php
namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Bookings\AzariAvailabilityEngine;
use App\Services\Bookings\AzariPricingEngine;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AzariAvailabilityController extends Controller {
    public function index(Request $r, AzariAvailabilityEngine $a, AzariPricingEngine $p) {
        $d=$r->validate([
            'check_in'=>['required','date'],'check_out'=>['required','date','after:check_in'],
            'adults'=>['required','integer','min:1'],'children'=>['nullable','integer','min:0'],
            'rooms'=>['nullable','integer','min:1'],'property_type'=>['nullable','string'],'location'=>['nullable','string'],
        ]);
        $in=CarbonImmutable::parse($d['check_in']); $out=CarbonImmutable::parse($d['check_out']);
        $q=Property::query()->where('is_published',true)
            ->when($d['property_type'] ?? null,fn($q,$v)=>$q->where('property_type',$v))
            ->when($d['location'] ?? null,fn($q,$v)=>$q->where('location',$v))
            ->orderBy('name')->get()->filter(function($property) use($a,$in,$out,$d){
                try{$a->assertRules($property,$in,$out,(int)$d['adults'],(int)($d['children']??0),(int)($d['rooms']??1));}
                catch(\Illuminate\Validation\ValidationException){return false;}
                return $a->available($property->getKey(),$in,$out);
            })->values();
        $results=$q->map(fn($property)=>['property'=>$property,'quote'=>$p->quote($property,$in,$out)]);
        $locations=Property::query()->where('is_published',true)->whereNotNull('location')->distinct()->orderBy('location')->pluck('location');
        return view('public.bookings.availability',compact('results','locations'))->with('filters',$d);
    }

    public function hold(Request $r, Property $property, AzariAvailabilityEngine $a) {
        $d=$r->validate(['check_in'=>['required','date'],'check_out'=>['required','date','after:check_in'],'adults'=>['required','integer','min:1'],'children'=>['nullable','integer','min:0'],'rooms'=>['nullable','integer','min:1']]);
        $h=$a->hold($property,CarbonImmutable::parse($d['check_in']),CarbonImmutable::parse($d['check_out']),(int)$d['adults'],(int)($d['children']??0),(int)($d['rooms']??1),$r->user()?->getKey());
        return redirect()->route('azari.booking.checkout',$h->token);
    }

    public function quote(Request $r, Property $property, AzariAvailabilityEngine $a, AzariPricingEngine $p) {
        $d=$r->validate(['check_in'=>['required','date'],'check_out'=>['required','date','after:check_in'],'adults'=>['required','integer','min:1'],'children'=>['nullable','integer','min:0'],'rooms'=>['nullable','integer','min:1'],'add_ons'=>['nullable','array']]);
        $in=CarbonImmutable::parse($d['check_in']); $out=CarbonImmutable::parse($d['check_out']);
        $a->assertRules($property,$in,$out,(int)$d['adults'],(int)($d['children']??0),(int)($d['rooms']??1));
        return response()->json(['available'=>$a->available($property->getKey(),$in,$out),'quote'=>$p->quote($property,$in,$out,$d['add_ons']??[])]);
    }
}
