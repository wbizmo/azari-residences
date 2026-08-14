<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint6BTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login_and_customer_receives_404_for_admin_surfaces(): void
    {
        $this->get('/azaridevadmin')
            ->assertRedirect(route('azari.admin.login'));

        $customer = User::factory()->create([
            'account_type' => 'customer',
            'is_admin' => false,
            'staff_role' => null,
            'is_active' => true,
            'status' => 'active',
            'suspended_at' => null,
        ]);

        $this->actingAs($customer)
            ->get('/azaridevadmin')
            ->assertNotFound();
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
