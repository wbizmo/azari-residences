<?php
namespace App\Notifications;
use App\Models\CommunicationLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
class PremiumMailNotification extends Notification implements ShouldQueue {
 use Queueable; public int $tries=3; public array $backoff=[60,300,900];
 public function __construct(public string $template,public string $subject,public array $lines=[],public ?string $actionLabel=null,public ?string $actionUrl=null,public array $context=[]){}
 public function via(object $notifiable):array{return ['mail'];}
 public function toMail(object $notifiable):MailMessage{
  $email=$notifiable instanceof AnonymousNotifiable ? (string)$notifiable->routeNotificationFor('mail') : (string)($notifiable->email??'');
  CommunicationLog::query()->create(['channel'=>'email','template'=>$this->template,'booking_id'=>$this->context['booking_id']??null,'user_id'=>$notifiable->id??null,'recipient'=>$email,'masked_recipient'=>$this->mask($email),'provider'=>config('mail.default'),'status'=>'queued','queued_at'=>now(),'meta'=>array_merge($this->context,['notification_id'=>$this->id])]);
  return (new MailMessage)->subject($this->subject)->view('emails.premium',['title'=>$this->subject,'lines'=>$this->lines,'actionLabel'=>$this->actionLabel,'actionUrl'=>$this->actionUrl]);
 }
 public function failed(?\Throwable $e):void{CommunicationLog::query()->where('meta->notification_id',$this->id)->update(['status'=>'failed','failed_at'=>now(),'safe_error'=>Str::limit((string)$e?->getMessage(),240)]);}
 private function mask(string $email):string{return preg_match('/^(.)(.*)(@.*)$/',$email,$m)?$m[1].'***'.$m[3]:'***';}
}
