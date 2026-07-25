<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use InvalidArgumentException;

final class PaymentManager
{
    /** @var array<string, PaymentProvider> */
    private array $providers;

    public function __construct(FlutterwaveService $flutterwave, PesapalService $pesapal, InTouchService $intouch)
    {
        $this->providers = [
            $flutterwave->name() => $flutterwave,
            $pesapal->name() => $pesapal,
            $intouch->name() => $intouch,
        ];
    }

    public function driver(string $provider): PaymentProvider
    {
        return $this->providers[strtolower($provider)] ?? throw new InvalidArgumentException("Unsupported payment provider: {$provider}");
    }

    /** @return array<string, PaymentProvider> */
    public function providers(): array { return $this->providers; }

    /** @return array<string, PaymentProvider> */
    public function enabledProviders(): array
    {
        return array_filter($this->providers, fn (PaymentProvider $provider) => $provider->enabled());
    }
}
