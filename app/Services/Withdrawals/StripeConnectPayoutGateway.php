<?php

namespace App\Services\Withdrawals;

use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeConnectPayoutGateway
{
    public function send(WithdrawalRequest $withdrawal): array
    {
        $secret = (string) config('services.stripe.secret');
        $account = (string) data_get($withdrawal->destination_snapshot, 'stripe_connected_account_id');

        if ($secret === '' || $account === '') {
            throw new RuntimeException('Stripe withdrawal credentials or connected account are missing.');
        }

        $response = Http::asForm()
            ->withBasicAuth($secret, '')
            ->timeout(30)
            ->post('https://api.stripe.com/v1/transfers', [
                'amount' => (int) round((float) $withdrawal->amount * 100),
                'currency' => strtolower($withdrawal->currency),
                'destination' => $account,
                'description' => 'Azari owner withdrawal '.$withdrawal->reference,
                'metadata[withdrawal_reference]' => $withdrawal->reference,
                'metadata[owner_user_id]' => (string) $withdrawal->user_id,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException((string) data_get($response->json(), 'error.message', 'Stripe transfer failed.'));
        }

        return [
            'reference' => (string) data_get($response->json(), 'id'),
            'status' => 'processed',
            'safe_response' => [
                'id' => data_get($response->json(), 'id'),
                'destination' => data_get($response->json(), 'destination'),
                'amount' => data_get($response->json(), 'amount'),
                'currency' => data_get($response->json(), 'currency'),
            ],
        ];
    }
}
