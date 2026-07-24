<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint6BTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_customer_receive_404_for_admin_surfaces(): void
    {
        $this->get('/azari-admin/bookings')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'is_active' => true]))->get('/azari-admin/bookings')->assertNotFound();
    }

    public function test_authorised_admin_can_open_paginated_booking_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $this->actingAs($admin)->get('/azari-admin/bookings')->assertOk();
    }

    public function test_admin_login_form_uses_the_login_field_expected_by_controller(): void
    {
        $this->get('/azaridevadmin/login')->assertOk()->assertSee('name="login"', false);
    }

    public function test_breeze_recovery_pages_use_azari_styling(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('az-auth-shell');
    }
}
