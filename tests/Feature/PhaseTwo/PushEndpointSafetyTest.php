<?php

namespace Tests\Feature\PhaseTwo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushEndpointSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_endpoints_must_use_external_https_services(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $valid = ['keys' => ['p256dh' => 'pubkey', 'auth' => 'authkey']];

        foreach ([
            'http://example.com/push',
            'https://localhost/push',
            'https://127.0.0.1/push',
            'https://[::1]/push',
            'https://service.internal/push',
            'https://user:secret@example.com/push',
            'https://example.com:4443/push',
        ] as $endpoint) {
            $this->actingAs($user)->postJson(route('user.push.subscribe'), [
                ...$valid, 'endpoint' => $endpoint,
            ])->assertUnprocessable();
        }

        $this->assertDatabaseCount('web_push_subscriptions', 0);

        $this->actingAs($user)->postJson(route('user.push.subscribe'), [
            ...$valid, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/safe-device-id',
        ])->assertOk();

        $this->assertDatabaseCount('web_push_subscriptions', 1);
    }
}
