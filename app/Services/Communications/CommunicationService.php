<?php
namespace App\Services\Communications;
use App\Models\{Booking,CommunicationLog,ServiceRequest,User}; use Illuminate\Support\Facades\{Http,Log,Mail};
class CommunicationService {
 public function email(string $to,string $template,string $subject,string $html,?Booking $booking=null,?User $user=null,?ServiceRequest $request=null): bool {
  $log=CommunicationLog::create(['channel'=>'email','template'=>$template,'booking_id'=>$booking?->id,'user_id'=>$user?->id,'service_request_id'=>$request?->id,'recipient'=>$to,'masked_recipient'=>$this->maskEmail($to),'provider'=>config('mail.default'),'status'=>'queued','queued_at'=>now()]);
  try{Mail::html($html,fn($m)=>$m->to($to)->subject($subject));$log->update(['status'=>'sent','sent_at'=>now()]);return true;}catch(\Throwable $e){Log::error('Azari email delivery failed',['log_id'=>$log->id,'template'=>$template,'exception'=>$e]);$log->update(['status'=>'failed','failed_at'=>now(),'safe_error'=>'The message could not be delivered.','retry_count'=>$log->retry_count+1]);return false;}
 }
 public function sms(string $to,string $template,string $message,?Booking $booking=null,?User $user=null): bool {
  $log=CommunicationLog::create(['channel'=>'sms','template'=>$template,'booking_id'=>$booking?->id,'user_id'=>$user?->id,'recipient'=>$to,'masked_recipient'=>$this->maskPhone($to),'provider'=>'twilio','status'=>'queued','queued_at'=>now()]);
  if(!config('services.twilio.enabled')){$log->update(['status'=>'skipped','safe_error'=>'SMS delivery is not enabled.']);return false;}
  try{$sid=config('services.twilio.sid');$token=config('services.twilio.token');$payload=['To'=>$to,'Body'=>mb_substr($message,0,1500)]; if(config('services.twilio.messaging_service_sid'))$payload['MessagingServiceSid']=config('services.twilio.messaging_service_sid');else $payload['From']=config('services.twilio.from');$r=Http::withBasicAuth($sid,$token)->asForm()->post("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json",$payload)->throw()->json();$log->update(['status'=>'sent','sent_at'=>now(),'provider_reference'=>$r['sid']??null]);return true;}catch(\Throwable $e){Log::error('Azari SMS delivery failed',['log_id'=>$log->id,'exception'=>$e]);$log->update(['status'=>'failed','failed_at'=>now(),'safe_error'=>'The text message could not be delivered.','retry_count'=>$log->retry_count+1]);return false;}
 }
 private function maskEmail(string $v):string{[$a,$d]=array_pad(explode('@',$v,2),2,'');return mb_substr($a,0,2).'***@'.$d;} private function maskPhone(string $v):string{return str_repeat('*',max(0,strlen($v)-4)).substr($v,-4);}
}
