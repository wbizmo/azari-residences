<?php

namespace App\Services\Payments;

use App\Models\PaymentProviderStatus;

class PaymentProviderHealthService
{
    public function __construct(private readonly PaymentManager $manager) {}

    public function refresh(?string $only = null): array
    {
        $result = [];
        foreach ($this->manager->providers() as $name => $provider) {
            if ($only && $name !== $only) continue;
            $check = $provider->healthCheck();
            $status = PaymentProviderStatus::query()->updateOrCreate(
                ['provider' => $name],
                [
                    'enabled' => $provider->enabled(),
                    'mode' => $provider->mode(),
                    'connection_status' => $check['successful'] ? 'successful' : ($provider->enabled() ? 'failed' : 'disabled'),
                    'last_checked_at' => now(),
                    'safe_message' => $check['message'] ?? null,
                ],
            );
            $result[$name] = $status;
        }
        return $result;
    }
}
