<?php

namespace App\Http\Controllers\UserArea;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Documents\BookingDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookingDocumentController extends Controller
{
    public function __invoke(
        Request $request,
        string $reference,
        string $type,
        BookingDocumentService $service
    ): Response {
        abort_unless(in_array($type, ['confirmation', 'invoice', 'receipt'], true), 404);

        $booking = $request->user()->bookings()
            ->with(['property', 'payments', 'user'])
            ->where('reference', $reference)
            ->first();

        abort_unless($booking, 403);

        $successfulPayment = $booking->payments
            ->where('status', Payment::SUCCESSFUL)
            ->sortByDesc(fn (Payment $payment) => $payment->paid_at ?: $payment->created_at)
            ->first();

        // A receipt is evidence of a successful payment and must not exist for unpaid bookings.
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
