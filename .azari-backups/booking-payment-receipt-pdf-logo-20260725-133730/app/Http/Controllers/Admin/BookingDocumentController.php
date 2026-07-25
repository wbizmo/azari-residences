<?php
namespace App\Http\Controllers\Admin; use App\Http\Controllers\Controller; use App\Models\Booking; use App\Services\Documents\BookingDocumentService;
class BookingDocumentController extends Controller {public function __invoke(Booking $booking,string $type,BookingDocumentService $svc){abort_unless(in_array($type,['confirmation','invoice','receipt']),404);$pdf=$svc->render($booking,$type);return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'inline; filename="'.$svc->filename($booking,$type).'"','Cache-Control'=>'private, no-store']);}}
