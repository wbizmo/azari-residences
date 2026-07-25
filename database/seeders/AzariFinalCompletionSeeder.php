<?php
namespace Database\Seeders;
use App\Models\{Permission,SiteSetting};
use Illuminate\Database\Seeder;
class AzariFinalCompletionSeeder extends Seeder {
 public function run():void{
  $groups=['support-tickets'=>['view','create','edit','export','manage'],'reviews'=>['view','edit','manage'],'promotions'=>['view','create','edit','manage'],'reports'=>['view','export'],'audit-logs'=>['view'],'system-health'=>['view','manage'],'cms'=>['view','edit']];
  foreach($groups as $module=>$actions)foreach($actions as $action)Permission::query()->updateOrCreate(['slug'=>"$module.$action"],['name'=>ucwords(str_replace('-',' ',$module)).' '.ucfirst($action),'group'=>$module]);
  $mail=(string)config('mail.from.address','');
  $settings=['public_contact_email'=>$mail,'customer_dashboard_contact_email'=>$mail,'support_destination_email'=>$mail,'service_request_destination_email'=>$mail,'contact_phone'=>'','whatsapp_number'=>'','support_hours'=>'Monday to Sunday, 8:00 AM to 8:00 PM','expected_response_time'=>'Within one business day','emergency_contact_notice'=>'For immediate danger, contact local emergency services.','public_timezone_wording'=>"Times are shown in Azari's operational timezone: ".config('azari.timezone','Africa/Lagos'),'receipt_footer'=>'Thank you for choosing THE AZARI RESIDENCES.','invoice_footer'=>'Payment is subject to the booking terms shown at confirmation.','service_request_email_display'=>$mail,'cancellation_wording'=>'Cancellation terms shown during booking apply.','payment_instructions'=>'Use only the payment options presented by Azari.','verification_explanatory_text'=>'This page verifies an Azari booking using privacy-safe information.','empty_state_bookings'=>'You do not have any bookings yet.','empty_state_payments'=>'No payment records are available yet.','document_legal_text'=>'This electronically generated document is valid with its verification reference.','booking_notice'=>''];
  foreach($settings as $k=>$v)if(SiteSetting::valueFor($k,null)===null)SiteSetting::put($k,$v,'textarea','customer_communications');
 }
}
