<?php

namespace App\Services\Documents;

use App\Models\Booking;
use App\Models\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class BookingDocumentService
{
    public function render(Booking $booking, string $type, ?Payment $payment = null): string
    {
        $booking->loadMissing(['property', 'payments', 'user']);

        if ($type === 'receipt') {
            /*
             |--------------------------------------------------------------
             | Use the booking's canonical payment resolver.
             |
             | This supports BOTH:
             |  - Normal successful Payment records.
             |  - Legacy/demo bookings that only have booking-level payment
             |    information (paid_at, receipt_number, payment_reference).
             |--------------------------------------------------------------
             */
            $payment ??= $booking->documentPayment();

            abort_unless(
                $payment instanceof Payment
                && $payment->status === Payment::SUCCESSFUL,
                404
            );
        } else {
            $payment ??= $booking->payments
                ->sortByDesc(fn (Payment $record) => $record->paid_at ?: $record->created_at)
                ->first();
        }

        $qr = '';

        try {
            $qr = Builder::create()
                ->writer(new SvgWriter())
                ->data(route('bookings.verify', [
                    'reference' => $booking->reference,
                ]))
                ->size(170)
                ->margin(0)
                ->build()
                ->getString();
        } catch (\Throwable $exception) {
            Log::warning('Azari booking document QR generation failed.', [
                'booking_reference' => $booking->reference,
                'document_type'     => $type,
                'exception'         => $exception::class,
                'message'           => $exception->getMessage(),
            ]);
        }

        $logoDataUri = $this->logoDataUri();

        $html = View::make('documents.booking-pdf', compact(
            'booking',
            'payment',
            'type',
            'qr',
            'logoDataUri'
        ))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function filename(Booking $booking, string $type): string
    {
        return strtolower($type) . '-' . $booking->reference . '.pdf';
    }

    private function logoDataUri(): ?string
    {
        // Dompdf renders embedded PNGs more reliably than SVG/WebP.
        $candidates = [
            public_path('images/logo-light.png'),
            public_path('images/logo-light.webp'),
        ];

        foreach ($candidates as $path) {
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            try {
                $contents = file_get_contents($path);

                if ($contents === false || $contents === '') {
                    continue;
                }

                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mime = $extension === 'webp'
                    ? 'image/webp'
                    : 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $exception) {
                Log::warning('Azari PDF logo embedding failed.', [
                    'logo_path' => $path,
                    'exception' => $exception::class,
                    'message'   => $exception->getMessage(),
                ]);
            }
        }

        Log::warning('Azari PDF logo was not found.', [
            'expected_paths' => $candidates,
        ]);

        return null;
    }
}