<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CommunicationLog;
use App\Services\Communication\TwilioRequestValidator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TwilioMessageStatusController extends Controller
{
    private const ORDER = [
        'accepted' => 10,
        'scheduled' => 10,
        'queued' => 20,
        'sending' => 30,
        'sent' => 40,
        'delivered' => 50,
        'read' => 60,
        'canceled' => 90,
        'failed' => 90,
        'undelivered' => 90,
    ];

    public function __invoke(Request $request, TwilioRequestValidator $validator): Response
    {
        abort_unless($validator->valid($request), 401);

        $data = $request->validate([
            'MessageSid' => ['required', 'string', 'max:64'],
            'MessageStatus' => ['required', 'string', 'max:32'],
            'ErrorCode' => ['nullable', 'string', 'max:32'],
        ]);

        $status = strtolower($data['MessageStatus']);
        $log = CommunicationLog::query()
            ->where('provider_reference', $data['MessageSid'])
            ->first();

        if (! $log) {
            return response('', 204);
        }

        $current = strtolower((string) ($log->provider_status ?: $log->status));
        $currentRank = self::ORDER[$current] ?? 0;
        $incomingRank = self::ORDER[$status] ?? 0;

        // Ignore stale/out-of-order callbacks while allowing terminal failures.
        if ($incomingRank < $currentRank && ! in_array($status, ['failed', 'undelivered', 'canceled'], true)) {
            return response('', 204);
        }

        $updates = [
            'provider_status' => $status,
            'status_updated_at' => now(),
            'provider_error_code' => $data['ErrorCode'] ?? null,
        ];

        if (in_array($status, ['delivered', 'read'], true)) {
            $updates += [
                'status' => 'delivered',
                'delivered_at' => $log->delivered_at ?: now(),
                'safe_error' => null,
            ];
        } elseif (in_array($status, ['failed', 'undelivered', 'canceled'], true)) {
            $updates += [
                'status' => 'failed',
                'failed_at' => now(),
                'safe_error' => 'Twilio reported that the message was not delivered.',
            ];
        } else {
            $updates += [
                'status' => in_array($status, ['sent'], true) ? 'sent' : 'queued',
                'sent_at' => $status === 'sent' ? ($log->sent_at ?: now()) : $log->sent_at,
            ];
        }

        $log->update($updates);

        return response('', 204);
    }
}
