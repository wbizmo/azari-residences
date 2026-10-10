<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BookingMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeBookingMessageAttachments extends Command
{
    protected $signature = 'resavar:purge-booking-attachments {--limit=200}';
    protected $description = 'Delete expired private booking-message attachments after completed or terminated stays.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $days = max(30, (int) config('azari.messaging.attachment_retention_days', 180));
        $cutoff = now()->subDays($days);

        $messages = BookingMessage::query()
            ->whereNotNull('attachment_path')
            ->whereNull('attachment_purged_at')
            ->where('created_at', '<=', $cutoff)
            ->whereIn('conversation_id', function ($query) use ($cutoff): void {
                $query->select('booking_conversations.id')
                    ->from('booking_conversations')
                    ->join('bookings', 'bookings.id', '=', 'booking_conversations.booking_id')
                    ->whereIn('bookings.status', ['completed', 'checked_out', 'cancelled', 'no_show'])
                    ->where('bookings.updated_at', '<=', $cutoff);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $purged = 0;
        foreach ($messages as $message) {
            $path = (string) $message->attachment_path;
            if ($path !== '' && Storage::disk('private')->exists($path)) {
                Storage::disk('private')->delete($path);
            }

            $updated = BookingMessage::query()
                ->whereKey($message->id)
                ->where('attachment_path', $path)
                ->whereNull('attachment_purged_at')
                ->update([
                    'attachment_path' => null,
                    'attachment_name' => null,
                    'attachment_scan_status' => 'purged',
                    'attachment_scan_error' => null,
                    'attachment_scan_claimed_at' => null,
                    'attachment_scan_next_attempt_at' => null,
                    'attachment_purged_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 1) {
                $message->refresh();
                AuditLog::record('booking_message.attachment_retention_purged', $message, [], [
                    'retention_days' => $days,
                ]);
                $purged++;
            }
        }

        $this->info("Purged {$purged} expired attachment(s).");

        return self::SUCCESS;
    }
}
