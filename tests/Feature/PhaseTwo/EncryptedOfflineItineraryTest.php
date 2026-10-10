<?php

namespace Tests\Feature\PhaseTwo;

use Tests\TestCase;

class EncryptedOfflineItineraryTest extends TestCase
{
    public function test_offline_trip_data_is_explicitly_consent_based_and_encrypted(): void
    {
        $script = file_get_contents(public_path('offline-trip.js'));
        $view = file_get_contents(resource_path('views/user/bookings/show.blade.php'));
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('PBKDF2', $script);
        $this->assertStringContainsString('AES-GCM', $script);
        $this->assertStringContainsString('310000', $script);
        $this->assertStringContainsString('ciphertext:', $script);
        $this->assertStringContainsString('data-offline-save', $view);
        $this->assertStringContainsString('offline_passphrase', $view);
        $this->assertStringContainsString('data-offline-trip-record', $view);
        $this->assertStringNotContainsString("'payment' =>", $view);
        $this->assertStringContainsString('data-offline-open', $offline);
        $this->assertStringContainsString('data-offline-clear', $offline);
    }

    public function test_service_worker_only_precaches_public_offline_files(): void
    {
        $worker = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("'/offline-trip.js'", $worker);
        $this->assertStringContainsString("request.mode === 'navigate'", $worker);
        $this->assertStringContainsString("if (request.method !== 'GET') return;", $worker);
        $this->assertStringNotContainsString('booking-details.json', $worker);
    }
}
