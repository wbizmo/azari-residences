<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog,SiteSetting};
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\View\View;
class CustomerContentController extends Controller {
 private array $keys=['public_contact_email','customer_dashboard_contact_email','support_destination_email','service_request_destination_email','contact_phone','whatsapp_number','support_hours','expected_response_time','emergency_contact_notice','receipt_footer','invoice_footer','service_request_email_display','public_timezone_wording','cancellation_wording','payment_instructions','verification_explanatory_text','empty_state_bookings','empty_state_payments','document_legal_text','booking_notice'];
 public function edit():View{$settings=collect($this->keys)->mapWithKeys(fn($k)=>[$k=>SiteSetting::valueFor($k,'')]);return view('admin.cms.customer-content',compact('settings'));}
 public function update(Request $r):RedirectResponse{$rules=array_fill_keys($this->keys,'nullable|string|max:5000');foreach(['public_contact_email','customer_dashboard_contact_email','support_destination_email','service_request_destination_email'] as $k)$rules[$k]='nullable|email|max:255';$data=$r->validate($rules);$old=collect($this->keys)->mapWithKeys(fn($k)=>[$k=>SiteSetting::valueFor($k,'')])->all();foreach($this->keys as $key)SiteSetting::put($key,$data[$key]??'','textarea','customer_communications');AuditLog::record('cms.customer_content_updated',null,$old,$data);return back()->with('success','Customer communication settings saved.');}
}
