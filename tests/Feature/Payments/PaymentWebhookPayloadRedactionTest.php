<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\PaymentWebhookProcessor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class PaymentWebhookPayloadRedactionTest extends TestCase
{
    public function test_safe_evidence_cannot_retain_nested_customer_card_token_or_credentials(): void
    {
        $processor = (new ReflectionClass(PaymentWebhookProcessor::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(PaymentWebhookProcessor::class, 'safePayload');

        $result = $method->invoke($processor, [
            'event' => 'charge.completed',
            'authorization' => ['token' => 'secret'],
            'customer' => ['email' => 'private@example.test'],
            'data' => [
                'id' => 37, 'tx_ref' => 'SAFE-MERCHANT-REF', 'amount' => 200,
                'currency' => 'NGN', 'status' => 'successful',
                'customer' => ['name' => 'Private Guest', 'email' => 'private@example.test'],
                'card' => ['number' => '4242424242424242'],
                'authorization' => ['authorization_code' => 'DANGER-KEY'],
                'token' => 'DANGER-TOKEN',
            ],
            'credentials' => ['password' => 'secret'],
        ]);

        $this->assertSame('charge.completed', $result['event']);
        $this->assertSame('SAFE-MERCHANT-REF', $result['data']['tx_ref']);
        $this->assertSame(200, $result['data']['amount']);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (['private@example.test', 'Private Guest', '4242424242424242', 'DANGER-KEY', 'DANGER-TOKEN', 'secret'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $encoded);
        }
        $this->assertArrayNotHasKey('authorization', $result);
        $this->assertArrayNotHasKey('customer', $result);
        $this->assertSame(['id', 'tx_ref', 'status', 'currency', 'amount'], array_keys($result['data']));
    }
}
