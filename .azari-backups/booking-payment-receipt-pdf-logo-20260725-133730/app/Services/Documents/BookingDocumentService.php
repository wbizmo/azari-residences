<?php
namespace App\Services\Documents;
use App\Models\Booking; use App\Models\Payment; use Dompdf\Dompdf; use Dompdf\Options; use Endroid\QrCode\Builder\Builder; use Endroid\QrCode\Writer\SvgWriter; use Illuminate\Support\Facades\View;
class BookingDocumentService {
 public function render(Booking $booking,string $type,?Payment $payment=null): string {
  $booking->loadMissing(['property','payments','user']); $payment ??= $booking->payments->first();
  $qr=''; try{$qr=Builder::create()->writer(new SvgWriter())->data(route('bookings.verify',['reference'=>$booking->reference]))->size(170)->margin(0)->build()->getString();}catch(\Throwable $e){report($e);}
  $html=View::make('documents.booking-pdf',compact('booking','payment','type','qr'))->render();
  $o=new Options();$o->set('isRemoteEnabled',false);$o->set('defaultFont','DejaVu Sans');$d=new Dompdf($o);$d->loadHtml($html);$d->setPaper('A4');$d->render();return $d->output();
 }
 public function filename(Booking $b,string $type):string{return strtolower($type).'-'.$b->reference.'.pdf';}
}
