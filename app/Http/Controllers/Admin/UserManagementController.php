<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
class UserManagementController extends Controller
{
    public function index(Request $request){
        $users=User::query()->when($request->filled('search'),fn($q)=>$q->where('name','like','%'.$request->search.'%')->orWhere('email','like','%'.$request->search.'%'))->latest()->paginate(config("azari.pagination.per_page", 10))->withQueryString();
        return view('admin.users.index',compact('users'));
    }
    public function suspend(Request $request, User $user){ $user->update(['status'=>'suspended','suspended_at'=>now(),'suspension_reason'=>$request->input('reason','Suspended by administrator')]); return back()->with('success','User suspended.'); }
    public function reactivate(User $user){ $user->update(['status'=>'active','suspended_at'=>null,'suspension_reason'=>null]); return back()->with('success','User reactivated.'); }
}
