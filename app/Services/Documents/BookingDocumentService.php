<?php

namespace App\Services\Documents;

use App\Models\Booking;
use App\Models\Payment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class BookingDocumentService
{
    public function render(Booking $booking, string $type, ?Payment $payment = null): string
    {
        $booking->loadMissing(['property', 'payments', 'user']);

        if ($type === 'receipt') {
            $payment ??= $booking->documentPayment();
            abort_unless($payment instanceof Payment && $payment->status === Payment::SUCCESSFUL, 404);
        } else {
            $payment ??= $booking->payments
                ->sortByDesc(fn (Payment $record) => $record->paid_at ?: $record->created_at)
                ->first();
        }

        $verificationUrl = route('bookings.verify', ['reference' => $booking->reference]);
        [$qrDataUri, $qrFallbackUrls] = $this->qrPayload($verificationUrl, $booking, $type);
        $logoDataUri = $this->logoDataUri();

        $html = View::make('documents.booking-pdf', compact(
            'booking', 'payment', 'type', 'qrDataUri', 'qrFallbackUrls',
            'verificationUrl', 'logoDataUri'
        ))->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('allowedRemoteHosts', [
            'api.qrserver.com',
            'quickchart.io',
            'api.qrcode-monkey.com',
        ]);
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

    private function qrPayload(string $url, Booking $booking, string $type): array
    {
        try {
            $result = (new Builder(
                writer: new PngWriter(),
                data: $url,
                size: 420,
                margin: 18,
            ))->build();

            $png = $result->getString();
            if ($png !== '') {
                return ['data:image/png;base64,' . base64_encode($png), []];
            }
        } catch (\Throwable $exception) {
            Log::warning('Azari local QR generation failed.', [
                'booking_reference' => $booking->reference,
                'document_type' => $type,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }

        $encoded = rawurlencode($url);

        return [null, [
            "https://api.qrserver.com/v1/create-qr-code/?size=420x420&margin=18&data={$encoded}",
            "https://quickchart.io/qr?size=420&margin=2&text={$encoded}",
            "https://api.qrcode-monkey.com/qr/custom?data={$encoded}&size=420&download=false&file=png",
        ]];
    }

    private function logoDataUri(): ?string
    {
        foreach ([public_path('images/logo-light.png'), public_path('images/logo-light.webp')] as $path) {
            if (! is_file($path) || ! is_readable($path)) continue;
            try {
                $contents = file_get_contents($path);
                if ($contents === false || $contents === '') continue;
                $mime = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'webp' ? 'image/webp' : 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode($contents);
            } catch (\Throwable $exception) {
                Log::warning('Azari PDF logo embedding failed.', [
                    'logo_path' => $path,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }
}
