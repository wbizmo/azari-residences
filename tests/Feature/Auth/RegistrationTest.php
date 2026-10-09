<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\AuthAbuseGuard;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 - Test Client']);
        $token = app(AuthAbuseGuard::class)->formToken('register');
        $this->travel(3)->seconds();

        $response = $this->post('/register', [
            '_auth_form_token' => $token,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'SecureTestPass2026',
            'password_confirmation' => 'SecureTestPass2026',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice'));
    }
}
