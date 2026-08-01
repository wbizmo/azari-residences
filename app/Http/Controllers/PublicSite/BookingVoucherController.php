<?php
namespace App\Http\Controllers\PublicSite;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\VoucherService;
use Illuminate\Http\Request;
class BookingVoucherController extends Controller {
    private function allowed(Request $r,Booking $b):void{$owned=$r->user()&&!$r->user()->isStaff()&&$b->user_id===$r->user()->id;$guest=(bool)$r->session()->get('azari_guest_bookings.'.$b->reference,false);abort_unless($owned||$guest,403);}
    public function store(Request $r,string $reference,VoucherService $service){$b=Booking::where('reference',$reference)->firstOrFail();$this->allowed($r,$b);$d=$r->validate(['voucher_code'=>'required|string|max:64']);$service->apply($b,$d['voucher_code']);return back()->with('success','Voucher applied.');}
    public function destroy(Request $r,string $reference,VoucherService $service){$b=Booking::where('reference',$reference)->firstOrFail();$this->allowed($r,$b);$service->remove($b);return back()->with('success','Voucher removed.');}
}
