<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BookingMessage;
use App\Services\Security\BookingAttachmentScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanBookingMessageAttachments extends Command
{
    protected $signature = 'resavar:scan-booking-attachments {--limit=25}';
    protected $description = 'Scan private booking attachments, releasing only clean files to participants.';

    public function handle(BookingAttachmentScanner $scanner): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $processed = 0;

        while ($processed < $limit) {
            $candidate = BookingMessage::query()
                ->whereNotNull('attachment_path')
                ->where(function ($query): void {
                    $query->where(function ($pending): void {
                        $pending->where('attachment_scan_status', 'pending')
                            ->where(function ($due): void {
                                $due->whereNull('attachment_scan_next_attempt_at')
                                    ->orWhere('attachment_scan_next_attempt_at', '<=', now());
                            });
                    })->orWhere(function ($stale): void {
                        $stale->where('attachment_scan_status', 'scanning')
                            ->where('attachment_scan_claimed_at', '<=', now()->subMinutes(15));
                    });
                })
                ->orderBy('id')
                ->first();

            if (! $candidate) {
                break;
            }

            // Atomic compare-and-swap prevents concurrent scanners from
            // scanning/releasing the same object simultaneously.
            $claimed = BookingMessage::query()->whereKey($candidate->id)
                ->where('attachment_scan_status', $candidate->attachment_scan_status)
                ->when($candidate->attachment_scan_status === 'scanning', fn ($q) =>
                    $q->where('attachment_scan_claimed_at', '<=', now()->subMinutes(15)))
                ->update([
                    'attachment_scan_status' => 'scanning',
                    'attachment_scan_claimed_at' => now(),
                    'attachment_scan_attempts' => $candidate->attachment_scan_attempts + 1,
                    'attachment_scan_next_attempt_at' => null,
                ]);

            if (! $claimed) {
                continue;
            }

            $processed++;
            $message = $candidate->fresh();

            try {
                $result = $scanner->scan($message->attachment_path);
                if ($result === 'infected') {
                    Storage::disk('private')->delete($message->attachment_path);
                    AuditLog::record('booking_message.attachment_quarantined', $message, [], [
                        'reason' => 'malware_detected',
                    ]);
                }

                $message->update([
                    'attachment_scan_status' => $result === 'clean' ? 'clean' : 'infected',
                    'attachment_scanned_at' => now(),
                    'attachment_scan_claimed_at' => null,
                    'attachment_scan_error' => null,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Booking attachment scanning failed', [
                    'message_id' => $message->id, 'exception' => $exception::class,
                ]);
                $exhausted = $message->attachment_scan_attempts >= 8;
                $backoff = min(3600, 60 * (2 ** max(0, $message->attachment_scan_attempts - 1)));
                $message->update([
                    'attachment_scan_status' => $exhausted ? 'error' : 'pending',
                    'attachment_scan_claimed_at' => null,
                    'attachment_scan_next_attempt_at' => $exhausted ? null : now()->addSeconds($backoff),
                    'attachment_scan_error' => mb_substr($exception::class, 0, 200),
                ]);
            }
        }

        $this->info("Processed {$processed} attachment(s).");

        return self::SUCCESS;
    }
}
