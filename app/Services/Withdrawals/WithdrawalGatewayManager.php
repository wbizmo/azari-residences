<?php

namespace App\Services\Withdrawals;

use App\Models\WithdrawalRequest;
use InvalidArgumentException;

class WithdrawalGatewayManager
{
    public function __construct(
        private readonly StripeConnectPayoutGateway $stripe,
        private readonly PayPalPayoutGateway $paypal,
    ) {}

    public function send(WithdrawalRequest $withdrawal): array
    {
        return match ($withdrawal->gateway) {
            'stripe' => $this->stripe->send($withdrawal),
            'paypal' => $this->paypal->send($withdrawal),
            default => throw new InvalidArgumentException('Unsupported withdrawal gateway.'),
        };
    }
}
