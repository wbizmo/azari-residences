<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\User;
use App\Services\Identity\DojahService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DojahIdentityVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('azari.identity.dojah.enabled', true);
        config()->set('azari.identity.dojah.secret_key', 'test-dojah-secret');
        config()->set('azari.identity.dojah.widget_id', '6a900eb834b449c195ebdbb5');
        config()->set('azari.identity.dojah.token_id', 'different-token-id');
        config()->set('azari.identity.dojah.required_steps', []);
    }

    public function test_account_identity_page_is_dojah_only(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('user.identity.index'));

        $response->assertOk();
        $response->assertSee('Dojah verification');
        $response->assertSee('Start secure verification');
        $response->assertDontSee('type="file"', false);
        $response->assertDontSee('Upload supporting ID');

        $this->assertFalse(Route::has('user.identity.store'));
        $this->assertFalse(Route::has('user.guests.identity.store'));
        $this->assertFalse(Route::has('azari.admin.identities.index'));
    }

    public function test_signed_dojah_success_verifies_the_account(): void
    {
        $user = User::factory()->create();
        $service = app(DojahService::class);
        $verification = $service->verificationForUser($user);

        $payload = [
            'event_id' => 'evt_verified_1',
            'reference_id' => $verification->reference,
            'verification_status' => 'verified',
            'data' => [
                'document' => ['status' => 'passed'],
                'selfie' => ['status' => 'passed'],
            ],
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $raw, 'test-dojah-secret');

        $this->assertTrue($service->verifyWebhookSignature($raw, $signature));

        $result = $service->processWebhook($payload, $raw);

        $this->assertNotNull($result);
        $this->assertTrue($result->fresh()->isVerified());
        $this->assertTrue($user->fresh()->hasVerifiedIdentity());
    }

    public function test_a_newer_failed_or_pending_result_cannot_be_bypassed_by_an_old_verified_record(): void
    {
        $user = User::factory()->create();

        IdentityVerification::query()->create([
            'user_id' => $user->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'old-verified-reference',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->assertTrue($user->hasVerifiedIdentity());

        IdentityVerification::query()->create([
            'user_id' => $user->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'new-pending-reference',
            'status' => IdentityVerification::STATUS_PENDING,
        ]);

        $this->assertFalse($user->fresh()->hasVerifiedIdentity());
    }

    public function test_protected_middleware_redirects_unverified_user_and_allows_verified_user(): void
    {
        Route::middleware(['web', 'auth', 'azari.identity.verified'])
            ->get('/__test/dojah-protected', fn () => response('ok'))
            ->name('__test.dojah.protected');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__test/dojah-protected')
            ->assertRedirect(route('user.identity.index'));

        IdentityVerification::query()->create([
            'user_id' => $user->id,
            'provider' => IdentityVerification::PROVIDER_DOJAH,
            'reference' => 'middleware-verified-reference',
            'status' => IdentityVerification::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/__test/dojah-protected')
            ->assertOk()
            ->assertSee('ok');
    }
}
