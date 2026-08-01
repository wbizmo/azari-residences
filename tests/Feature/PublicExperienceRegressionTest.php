<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicExperienceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_pages_use_compact_standalone_shell(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('az-standalone-auth-shell', false)
            ->assertDontSee('site-header', false);
    }

    public function test_public_editorial_pages_render_their_assigned_images(): void
    {
        $this->get(route('public.about'))->assertOk()->assertSee('team-azari.png');
        $this->get(route('public.contact'))->assertOk()->assertSee('contact-azari.png');
        $this->get(route('public.local-guide'))->assertOk()->assertSee('local-guides-azari.png');
        $this->get(route('public.concierge'))->assertOk()->assertSee('azari-concierge.png');
        $this->get(route('public.housekeeping'))->assertOk()->assertSee('azari-housekeeping.png');
        $this->get(route('public.restaurant'))->assertOk()->assertSee('azari-food.png');
        $this->get(route('public.airport-transfers'))->assertOk()->assertSee('azari-airport.png');
    }

    public function test_contact_form_uses_mail_transport_and_environment_recipient(): void
    {
        Mail::fake();
        config(['mail.contact_to' => 'contact@example.test']);

        $this->post(route('public.contact.submit'), [
            'name' => 'Test Guest',
            'email' => 'guest@example.test',
            'phone' => '+2347000000000',
            'subject' => 'Booking question',
            'message' => 'Please send more information about a stay.',
        ])->assertRedirect()->assertSessionHas('success');

        Mail::assertSentCount(1);
    }
}
