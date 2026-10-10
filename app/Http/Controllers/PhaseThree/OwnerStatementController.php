<?php

namespace App\Http\Controllers\PhaseThree;

use App\Http\Controllers\Controller;
use App\Services\PhaseThree\OwnerStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OwnerStatementController extends Controller
{
    public function __invoke(Request $request, OwnerStatementService $statements): StreamedResponse
    {
        $data = $request->validate([
            'currency' => ['required','string','size:3','regex:/^[A-Z]{3}$/'],
            'from' => ['required','date'], 'to' => ['required','date','after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to = CarbonImmutable::parse($data['to'])->endOfDay();
        $rows = $statements->entries($request->user(), $data['currency'], $from, $to);
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Date (UTC)','Reference','Type','Direction','Amount','Currency','Booking ID','Payment ID']);
            foreach ($rows as $row) {
                // Prefix formula-looking values to avoid spreadsheet injection.
                $clean = fn ($value) => preg_match('/^[=+@\-\t\r]/', (string) $value)
                    ? "'".$value : (string) $value;
                fputcsv($stream, [
                    $row->created_at?->utc()->format('Y-m-d H:i:s'),
                    $clean($row->reference), $clean($row->type), $row->direction,
                    $row->amount, $row->currency, $row->booking_id, $row->payment_id,
                ]);
            }
            fclose($stream);
        }, 'resavar-owner-statement-'.$data['from'].'-'.$data['to'].'-'.$data['currency'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
