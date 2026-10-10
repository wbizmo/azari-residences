<?php

namespace App\Console\Commands;

use App\Models\BookingMessageAlertOutbox;
use App\Notifications\PremiumMailNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliverBookingMessageAlerts extends Command
{
    protected $signature = 'resavar:deliver-booking-message-alerts {--limit=50}';
    protected $description = 'Queue durable in-app/email booking message alerts, retrying interrupted dispatch safely.';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $processed = 0;

        while ($processed < $limit) {
            $item = DB::transaction(function (): ?BookingMessageAlertOutbox {
                $item = BookingMessageAlertOutbox::query()
                    ->where(function ($query): void {
                        $query->where(function ($pending): void {
                            $pending->whereIn('state', ['pending', 'retry'])
                                ->where(function ($due): void {
                                    $due->whereNull('next_attempt_at')
                                        ->orWhere('next_attempt_at', '<=', now());
                                });
                        })->orWhere(function ($stale): void {
                            $stale->where('state', 'processing')
                                ->where('claimed_at', '<=', now()->subMinutes(15));
                        });
                    })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    return null;
                }

                $item->update([
                    'state' => 'processing',
                    'attempts' => $item->attempts + 1,
                    'claimed_at' => now(),
                    'next_attempt_at' => null,
                ]);

                return $item;
            }, 3);

            if (! $item) {
                break;
            }

            $processed++;

            try {
                $item->load(['message', 'booking', 'recipient']);
                if (! $item->message || ! $item->booking || ! $item->recipient) {
                    $item->update(['state' => 'skipped', 'claimed_at' => null]);
                    continue;
                }

                $guestMessage = $item->message->sender_type === 'guest';
                $notification = $guestMessage
                    ? new PremiumMailNotification(
                        'booking-message',
                        'A guest sent a message about a Resavar reservation',
                        ['A new message is waiting in your property inbox. Sign in to read it securely.'],
                        'Open conversation',
                        route('user.owner.phase2.messages', ['property' => $item->booking->property_id]),
                        ['booking_id' => $item->booking_id]
                    )
                    : new PremiumMailNotification(
                        'booking-message',
                        'Your property team sent a Resavar message',
                        ['A new message is available for your reservation. Sign in to read it securely.'],
                        'Read message',
                        route('user.bookings.phase2.messages', ['reference' => $item->booking->reference]),
                        ['booking_id' => $item->booking_id]
                    );

                $item->recipient->notify($notification);
                $item->update([
                    'state' => 'queued',
                    'queued_at' => now(),
                    'claimed_at' => null,
                    'last_error' => null,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Unable to queue booking message alert', [
                    'outbox_id' => $item->getKey(),
                    'exception' => $exception::class,
                ]);
                $exhausted = $item->attempts >= 8;
                $backoff = min(3600, 60 * (2 ** max(0, $item->attempts - 1)));
                $item->update([
                    'state' => $exhausted ? 'failed' : 'retry',
                    'claimed_at' => null,
                    'next_attempt_at' => $exhausted ? null : now()->addSeconds($backoff),
                    'last_error' => mb_substr($exception::class, 0, 200),
                ]);
            }
        }

        $this->info("Processed {$processed} booking message alert(s).");

        return self::SUCCESS;
    }
}
