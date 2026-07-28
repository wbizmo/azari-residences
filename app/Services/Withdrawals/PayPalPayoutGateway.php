<?php

namespace App\Services\Withdrawals;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalPayoutGateway
{
    public function send(WithdrawalRequest $withdrawal): array
    {
        $clientId = (string) config('services.paypal.client_id');
        $secret = (string) config('services.paypal.secret');
        $base = rtrim((string) config('services.paypal.base_url', 'https://api-m.sandbox.paypal.com'), '/');

        if ($clientId === '' || $secret === '') {
            throw new RuntimeException('PayPal Payouts credentials are missing.');
        }

        $tokenResponse = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->timeout(30)
            ->post($base.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('PayPal authentication failed.');
        }

        $recipient = (string) data_get($withdrawal->destination_snapshot, 'paypal_recipient');
        $recipientType = strtoupper((string) data_get($withdrawal->destination_snapshot, 'paypal_recipient_type', 'EMAIL'));

        if ($recipient === '') {
            throw new RuntimeException('PayPal payout recipient is missing.');
        }

        $response = Http::withToken((string) $tokenResponse->json('access_token'))
            ->acceptJson()
            ->timeout(30)
            ->post($base.'/v1/payments/payouts', [
                'sender_batch_header' => [
                    'sender_batch_id' => $withdrawal->reference,
                    'email_subject' => 'Your Azari property earnings withdrawal',
                    'email_message' => 'Your Azari property earnings withdrawal is being processed.',
                ],
                'items' => [[
                    'recipient_type' => $recipientType,
                    'amount' => [
                        'value' => number_format((float) $withdrawal->amount, 2, '.', ''),
                        'currency' => strtoupper($withdrawal->currency),
                    ],
                    'receiver' => $recipient,
                    'note' => 'Azari owner withdrawal '.$withdrawal->reference,
                    'sender_item_id' => $withdrawal->reference,
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException((string) data_get($response->json(), 'message', 'PayPal payout failed.'));
        }

        return [
            'reference' => (string) data_get($response->json(), 'batch_header.payout_batch_id'),
            'status' => 'processed',
            'safe_response' => [
                'payout_batch_id' => data_get($response->json(), 'batch_header.payout_batch_id'),
                'batch_status' => data_get($response->json(), 'batch_header.batch_status'),
            ],
        ];
    }
}
