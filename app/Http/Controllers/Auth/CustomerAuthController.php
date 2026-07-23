<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
class CustomerAuthController extends Controller
{
    public function create(){ return view('auth.customer-register'); }
    public function store(Request $request){
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','unique:users,email'],'password'=>['required','confirmed',Password::defaults()]]);
        $user=User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password']),'account_type'=>'customer','status'=>'active']);
        Auth::login($user);
        return redirect('/')->with('success','Guest account created.');
    }
}
