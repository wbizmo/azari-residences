<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog,Booking,CommunicationLog,Payment,PaymentProviderStatus,Property,ServiceRequest,SupportTicket};
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{Request,Response};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
class ReportController extends Controller {
 public const TYPES=['bookings','payments','failed_payments','cancellations','no_shows','guests','property_performance','occupancy','revenue','service_requests','concierge','housekeeping','restaurant','airport_transfer','maintenance','support_tickets','email_delivery','sms_delivery','provider_performance'];
 public function index(Request $r):View{$type=$this->type($r);$rows=$this->query($type,$r)->paginate(10)->withQueryString();return view('admin.reports.index',['type'=>$type,'rows'=>$rows,'types'=>self::TYPES]);}
 public function print(Request $r):View{$type=$this->type($r);$rows=$this->query($type,$r)->limit(5000)->get();return view('admin.reports.print',compact('type','rows'));}
 public function export(Request $r,string $format):Response|StreamedResponse{
  abort_unless(in_array($format,['csv','excel','pdf'],true),404);$type=$this->type($r);$rows=$this->query($type,$r)->limit(10000)->get()->map(fn($x)=>(array)$x->getAttributes());$headers=array_keys((array)($rows->first()??[]));AuditLog::record('report.exported',null,[],[],['type'=>$type,'format'=>$format]);
  if($format==='pdf'){$html=view('admin.reports.print',compact('type','rows'))->render();$pdf=new Dompdf(['isRemoteEnabled'=>false]);$pdf->loadHtml($html);$pdf->setPaper('A4','landscape');$pdf->render();return response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="azari-'.$type.'-'.now()->format('Ymd-His').'.pdf"']);}
  if($format==='excel')return response($this->spreadsheetXml($type,$headers,$rows),200,['Content-Type'=>'application/vnd.ms-excel; charset=UTF-8','Content-Disposition'=>'attachment; filename="azari-'.$type.'-'.now()->format('Ymd-His').'.xls"']);
  return response()->streamDownload(function()use($headers,$rows){$out=fopen('php://output','w');fputcsv($out,$headers);foreach($rows as $row)fputcsv($out,array_map([$this,'scalar'],$row));fclose($out);},'azari-'.$type.'-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8']);
 }
 private function type(Request $r):string{$type=(string)$r->get('type','bookings');abort_unless(in_array($type,self::TYPES,true),404);return $type;}
 private function apply(Builder $q,Request $r,string $date='created_at'):Builder{
  if($r->filled('from'))$q->whereDate($date,'>=',$r->date('from'));if($r->filled('to'))$q->whereDate($date,'<=',$r->date('to'));
  foreach(['status','currency','provider','booking_id','property_id','assigned_to'] as $field)if($r->filled($field)&&$q->getModel()->getConnection()->getSchemaBuilder()->hasColumn($q->getModel()->getTable(),$field))$q->where($field,$r->input($field));
  return $q;
 }
 private function query(string $type,Request $r):Builder{
  if(in_array($type,['payments','failed_payments'],true)){$q=Payment::query()->select('reference','booking_id','user_id','provider','provider_reference','status','amount','currency','paid_at','verified_at','failed_at','created_at');if($type==='failed_payments')$q->where('status','failed');return $this->apply($q,$r);}
  if($type==='cancellations')return $this->apply(Booking::query()->whereNotNull('cancelled_at')->select('reference','property_id','user_id','guest_name','status','currency','total','cancelled_at','cancellation_reason','created_at'),$r,'cancelled_at');
  if($type==='no_shows')return $this->apply(Booking::query()->whereNotNull('no_show_at')->select('reference','property_id','guest_name','status','no_show_at','created_at'),$r,'no_show_at');
  if($type==='guests')return $this->apply(Booking::query()->select('reference','property_id','user_id','guest_name','guest_email','guest_phone','adults','children','status','check_in','check_out','created_at'),$r);
  if(in_array($type,['service_requests','concierge','housekeeping','restaurant','airport_transfer','maintenance'],true)){$q=ServiceRequest::query()->select('reference','booking_id','user_id','assigned_to','type','title','status','requested_at','created_at');if($type!=='service_requests')$q->where('type',$type);return $this->apply($q,$r);}
  if($type==='support_tickets')return $this->apply(SupportTicket::query()->select('reference','user_id','booking_id','assigned_to','category','subject','status','priority','first_responded_at','resolved_at','created_at'),$r);
  if(in_array($type,['email_delivery','sms_delivery'],true))return $this->apply(CommunicationLog::query()->where('channel',$type==='email_delivery'?'email':'sms')->select('template','booking_id','user_id','masked_recipient','provider','status','retry_count','queued_at','sent_at','delivered_at','failed_at','created_at'),$r);
  if($type==='provider_performance')return PaymentProviderStatus::query()->select('provider','enabled','mode','connection_status','last_webhook_at','last_successful_payment_at','last_error','updated_at as created_at')->latest();
  if(in_array($type,['property_performance','occupancy','revenue'],true))return Booking::query()->selectRaw(
   'property_id, currency,
    COUNT(*) as bookings_count,
    SUM(CASE WHEN status IN (?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as occupied_count,
    SUM(CASE WHEN payment_status IN (?, ?, ?) THEN total ELSE 0 END) as revenue_total,
    MIN(created_at) as period_start,
    MAX(created_at) as period_end,
    MAX(created_at) as created_at',
   [
    'confirmed',
    'checked_in',
    'active',
    'checked_out',
    'completed',
    'paid',
    'completed',
    'successful',
   ]
  )->groupBy('property_id','currency')->orderByDesc('created_at');
  return $this->apply(Booking::query()->select('reference','property_id','user_id','guest_name','status','payment_status','currency','total','check_in','check_out','created_at'),$r);
 }
 private function scalar(mixed $v):mixed{return is_scalar($v)||$v===null?$v:json_encode($v);}
 private function spreadsheetXml(string $type,array $headers,Collection $rows):string{$esc=fn($v)=>htmlspecialchars((string)$this->scalar($v),ENT_XML1|ENT_QUOTES,'UTF-8');$xml='<?xml version="1.0" encoding="UTF-8"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Worksheet ss:Name="'.$esc(substr($type,0,31)).'"><Table><Row>';foreach($headers as $h)$xml.='<Cell><Data ss:Type="String">'.$esc($h).'</Data></Cell>';$xml.='</Row>';foreach($rows as $row){$xml.='<Row>';foreach($headers as $h)$xml.='<Cell><Data ss:Type="String">'.$esc($row[$h]??'').'</Data></Cell>';$xml.='</Row>';}$xml.='</Table></Worksheet></Workbook>';return $xml;}
}
