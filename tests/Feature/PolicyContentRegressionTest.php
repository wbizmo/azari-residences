<?php

namespace Tests\Feature;

use Tests\TestCase;

class PolicyContentRegressionTest extends TestCase
{
    public function test_policy_pages_publish_complete_guest_information(): void
    {
        $expectations = [
            '/booking-terms' => [
                'Guest identity and identification',
                'does not use OCR, facial recognition or third-party automated identity verification',
                'A room or apartment cannot be transferred from one booking to another',
            ],
            '/cancellation-policy' => [
                'Reservation-specific conditions',
                'No transfer between bookings',
                'separate booking reference',
            ],
            '/privacy-policy' => [
                'Identification documents',
                'does not perform optical character recognition, facial recognition or third-party automated identity verification',
                'Guest names may be masked',
            ],
            '/terms-and-conditions' => [
                'Acceptable use',
                'Digital check-in and guest conduct',
                'Rooms, apartments and payments cannot be transferred between bookings',
            ],
        ];

        foreach ($expectations as $uri => $copy) {
            $response = $this->get($uri);
            $response->assertOk();

            foreach ($copy as $text) {
                $response->assertSee($text);
            }
        }
    }

    public function test_standalone_authentication_shell_keeps_centering_hooks(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('az-auth-page', false)
            ->assertSee('az-auth-main', false)
            ->assertSee('az-auth-card', false);
    }
}
