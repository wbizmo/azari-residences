<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class VoucherController extends Controller {
 public function index(){return view('admin.vouchers.index',['vouchers'=>Voucher::withCount('redemptions')->latest()->paginate(10),'properties'=>Property::orderBy('name')->get()]);}
 public function store(Request $r){$d=$this->validated($r);$properties=$d['property_ids']??[];unset($d['property_ids']);$d['code']=strtoupper($d['code']);$d['created_by']=$r->user() && \App\Models\User::whereKey($r->user()->getKey())->exists()?$r->user()->getKey():null;$v=Voucher::create($d);$v->properties()->sync($properties);AuditLog::record('voucher.created',$v);return back()->with('success','Voucher created.');}
 public function update(Request $r,Voucher $voucher){$d=$this->validated($r,$voucher);$properties=$d['property_ids']??[];unset($d['property_ids']);$d['code']=strtoupper($d['code']);$voucher->update($d);$voucher->properties()->sync($properties);AuditLog::record('voucher.updated',$voucher);return back()->with('success','Voucher updated.');}
 private function validated(Request $r,?Voucher $v=null):array{return $r->validate(['code'=>['required','alpha_dash','max:64',Rule::unique('vouchers','code')->ignore($v)],'name'=>'required|string|max:160','discount_type'=>'required|in:percentage,fixed','discount_value'=>'required|numeric|min:0.01','maximum_discount'=>'nullable|numeric|min:0.01','minimum_booking_value'=>'nullable|numeric|min:0','starts_at'=>'nullable|date','expires_at'=>'nullable|date|after:starts_at','total_usage_limit'=>'nullable|integer|min:1','per_customer_limit'=>'required|integer|min:1','is_active'=>'required|boolean','property_ids'=>'nullable|array','property_ids.*'=>'integer|exists:properties,id']);}
}
