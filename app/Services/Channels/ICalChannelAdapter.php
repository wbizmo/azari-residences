<?php

namespace App\Services\Channels;

use App\Contracts\Channels\ChannelAdapter;
use App\Models\Booking;
use App\Models\ChannelConnection;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ICalChannelAdapter implements ChannelAdapter
{
    public function __construct(private readonly ChannelFeedUrlValidator $urls) {}

    public function import(ChannelConnection $connection): array
    {
        if (! $connection->import_url) {
            throw new \RuntimeException('External channel is missing its calendar import URL.');
        }
        $this->urls->assertSafe($connection->import_url);
        $body = Http::withOptions(['allow_redirects' => false])->timeout(12)->connectTimeout(5)->retry(2, 500, throw: false)->get($connection->import_url);
        if (! $body->successful()) {
            throw new \RuntimeException('External calendar returned HTTP '.$body->status().'.');
        }
        if (strlen($body->body()) > 2_000_000) throw new \RuntimeException('External calendar exceeds the safe import size.');
        return $this->parse($body->body());
    }

    /** @return array<int, array<string, mixed>> */
    public function parse(string $ical): array
    {
        if (strlen($ical) > 2_000_000) {
            throw new \RuntimeException('External calendar exceeds the safe import size.');
        }
        if (! str_contains($ical, 'BEGIN:VCALENDAR') || ! str_contains($ical, 'END:VCALENDAR')) {
            throw new \RuntimeException('External calendar is missing required calendar boundaries.');
        }
        $ical = preg_replace("/\r?\n[ \t]/", '', $ical) ?? $ical;
        preg_match_all('/BEGIN:VEVENT\R(.*?)\REND:VEVENT/s', $ical, $matches);
        // Reject partially truncated calendars even when some earlier events
        // parse correctly. A partial feed is not evidence that missing remote
        // reservations were cancelled.
        if (substr_count($ical, 'BEGIN:VEVENT') !== count($matches[1] ?? [])
            || substr_count($ical, 'END:VEVENT') !== count($matches[1] ?? [])) {
            throw new \RuntimeException('External calendar has incomplete reservation blocks.');
        }
        if (count($matches[1] ?? []) > 5000) {
            throw new \RuntimeException('External calendar contains too many events.');
        }
        $events = [];
        foreach ($matches[1] ?? [] as $block) {
            $fields = [];
            foreach (preg_split('/\R/', trim($block)) ?: [] as $line) {
                if (! str_contains($line, ':')) continue;
                [$key, $value] = explode(':', $line, 2);
                $key = strtoupper(explode(';', $key, 2)[0]);
                $fields[$key] = trim($value);
            }
            if (blank($fields['UID'] ?? null) || blank($fields['DTSTART'] ?? null)
                || blank($fields['DTEND'] ?? null)) {
                throw new \RuntimeException('External calendar contains an incomplete reservation.');
            }
            $start = $this->date($fields['DTSTART']);
            $end = $this->date($fields['DTEND']);
            if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
                throw new \RuntimeException('External calendar contains an invalid reservation date range.');
            }
            $status = strtoupper((string) ($fields['STATUS'] ?? 'CONFIRMED')) === 'CANCELLED' ? 'cancelled' : 'active';
            $events[] = [
                'external_id' => mb_substr($fields['UID'], 0, 255),
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'status' => $status,
                'quantity' => 1,
                'summary' => mb_substr((string) ($fields['SUMMARY'] ?? 'External reservation'), 0, 255),
                'external_updated_at' => $this->dateTime($fields['LAST-MODIFIED'] ?? null),
                'source_hash' => hash('sha256', $block),
            ];
        }
        // A calendar with VEVENT rows but no parsable reservation must be
        // treated as corrupt, not as a legitimate zero-reservation snapshot.
        if ($events === [] && str_contains($ical, 'BEGIN:VEVENT')) {
            throw new \RuntimeException('External calendar events could not be parsed safely.');
        }
        return $events;
    }

    public function export(Property $property, ?int $accommodationTypeId = null): string
    {
        $bookings = Booking::query()
            ->where('property_id', $property->getKey())
            ->when($accommodationTypeId, fn($q, $id) => $q->where('accommodation_type_id', $id))
            ->whereNotIn('status', ['cancelled', 'expired', 'rejected'])
            ->whereDate('check_out', '>=', now()->subDay()->toDateString())
            ->orderBy('check_in')->get(['reference','check_in','check_out','updated_at']);

        $lines = ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Resarva//Inventory Calendar//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH'];
        foreach ($bookings as $booking) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.Str::ascii((string) $booking->reference).'@resarva';
            $lines[] = 'DTSTART;VALUE=DATE:'.$booking->check_in->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$booking->check_out->format('Ymd');
            $lines[] = 'DTSTAMP:'.$booking->updated_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:Reserved';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines)."\r\n";
    }

    private function date(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('!Ymd', substr($value, 0, 8), 'UTC');
            return $date ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
    private function dateTime(?string $value): ?CarbonImmutable
    {
        if (! $value) return null;
        try { return CarbonImmutable::parse($value, 'UTC'); } catch (\Throwable) { return null; }
    }
}
