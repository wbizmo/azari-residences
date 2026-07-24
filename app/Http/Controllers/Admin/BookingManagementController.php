<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
class BookingManagementController extends Controller
{
    public function index(Request $request){ $bookings=Booking::query()->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))->latest()->paginate(config("azari.pagination.per_page", 10))->withQueryString(); return view('admin.bookings.index',compact('bookings')); }
    public function calendar(){ return view('admin.bookings.calendar',['bookings'=>Booking::whereNot('status','cancelled')->orderBy('check_in')->get()]); }
    public function transition(Request $request, Booking $booking){ $data=$request->validate(['status'=>['required','in:pending,approved,confirmed,checked_in,checked_out,cancelled']]); $booking->update(['status'=>$data['status'],'approved_at'=>$data['status']==='approved'?now():$booking->approved_at,'cancelled_at'=>$data['status']==='cancelled'?now():$booking->cancelled_at]); return back()->with('success','Booking updated.'); }
}
