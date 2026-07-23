<?php

namespace Tests\Feature\Foundation;

use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_boots_successfully(): void
    {
        $this->get('/')->assertSuccessful();
    }

    public function test_payment_manager_is_registered(): void
    {
        $this->assertInstanceOf(
            PaymentManager::class,
            app(PaymentManager::class)
        );
    }

    public function test_private_filesystem_is_configured(): void
    {
        $this->assertSame(
            storage_path('app/private'),
            config('filesystems.disks.private.root')
        );
    }

    public function test_generated_links_use_configured_application_url(): void
    {
        $this->assertStringStartsWith(
            rtrim((string) config('app.url'), '/'),
            url('/')
        );
    }
}