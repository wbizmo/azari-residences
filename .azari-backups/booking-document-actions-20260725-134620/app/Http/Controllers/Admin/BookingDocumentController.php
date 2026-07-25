<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Documents\BookingDocumentService;
use Symfony\Component\HttpFoundation\Response;

class BookingDocumentController extends Controller
{
    public function __invoke(
        Booking $booking,
        string $type,
        BookingDocumentService $service
    ): Response {
        abort_unless(in_array($type, ['confirmation', 'invoice', 'receipt'], true), 404);

        $booking->loadMissing(['property', 'payments', 'user']);

        $successfulPayment = $booking->payments
            ->where('status', Payment::SUCCESSFUL)
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at ?: $payment->created_at)
            ->first();

        if ($type === 'receipt') {
            abort_unless($successfulPayment, 404);
        }

        $pdf = $service->render($booking, $type, $successfulPayment);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$service->filename($booking, $type).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
