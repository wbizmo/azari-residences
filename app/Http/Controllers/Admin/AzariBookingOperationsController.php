<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\MaintenancePeriod;
use App\Services\Bookings\AzariBookingLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AzariBookingOperationsController extends Controller {
    public function index(Request $r) {
        $bookings=Booking::query()->with(['property','user'])
            ->when($r->filled('status'),fn($q)=>$q->where('status',$r->string('status')))
            ->when($r->filled('search'),function($q) use($r){$s='%'.$r->string('search')->trim().'%';$q->where(fn($x)=>$x->where('reference','like',$s)->orWhere('guest_name','like',$s)->orWhere('guest_email','like',$s));})
            ->latest()->paginate(config('azari.pagination.per_page',10))->withQueryString();
        return view('admin.bookings.operations',compact('bookings'));
    }

    public function calendar(Request $r) {
        $month=CarbonImmutable::parse($r->input('month',now()->format('Y-m-01')))->startOfMonth();
        $bookings=Booking::query()->with('property')->whereDate('check_in','<=',$month->endOfMonth())->whereDate('check_out','>=',$month)->orderBy('check_in')->paginate(10)->withQueryString();
        $maintenance=MaintenancePeriod::query()->with('property')->whereDate('starts_on','<=',$month->endOfMonth())->whereDate('ends_on','>=',$month)->orderBy('starts_on')->paginate(10,['*'],'maintenance_page')->withQueryString();
        $holds=BookingHold::query()->active()->with('property')->orderBy('check_in')->paginate(10,['*'],'holds_page')->withQueryString();
        return view('admin.bookings.availability-calendar',compact('month','bookings','maintenance','holds'));
    }

    public function transition(Request $r, Booking $booking, AzariBookingLifecycle $l) {
        $d=$r->validate(['status'=>['required','string'],'note'=>['nullable','string','max:2000']]);
        if ($d['status'] === 'cancelled') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Cancel bookings through the dedicated audited cancellation workflow.',
            ]);
        }
        $l->transition($booking,$d['status'],$r->user()?->getKey(),$d['note']??null);
        return back()->with('success','Booking status updated.');
    }

    public function storeMaintenance(Request $r) {
        $d=$r->validate(['property_id'=>['required','exists:properties,id'],'starts_on'=>['required','date'],'ends_on'=>['required','date','after:starts_on'],'title'=>['required','string','max:160'],'notes'=>['nullable','string'],'blocks_booking'=>['nullable','boolean']]);
        MaintenancePeriod::query()->create([...$d,'blocks_booking'=>$r->boolean('blocks_booking'),'created_by'=>$r->user()?->getKey()]);
        return back()->with('success','Maintenance period added.');
    }

    public function destroyMaintenance(MaintenancePeriod $maintenancePeriod) {
        $maintenancePeriod->delete();
        return back()->with('success','Maintenance period removed.');
    }
}
