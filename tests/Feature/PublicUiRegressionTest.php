<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUiRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_now_page_uses_public_layout_and_redesigned_form(): void
    {
        $response = $this->get(route('availability.index'));

        $response
            ->assertOk()
            ->assertSee('az-book-page', false)
            ->assertSee('az-book-grid', false)
            ->assertSee('az-book-media', false)
            ->assertSee('az-book-panel', false)
            ->assertSee('data-availability-form', false)
            ->assertSee('name="check_in"', false)
            ->assertSee('name="check_out"', false)
            ->assertSee('name="adults"', false)
            ->assertSee('name="children"', false)
            ->assertSee('name="location_id"', false)
            ->assertSee('name="room_type_id"', false)
            ->assertSee('name="rooms"', false);
    }

    public function test_password_recovery_page_uses_centered_authentication_shell(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('az-auth-shell', false)
            ->assertSee('az-auth-page', false)
            ->assertSee('az-auth-main', false)
            ->assertSee('az-auth-card', false);
    }
}
