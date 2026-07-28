<?php

namespace Tests\Feature\Architecture;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class PhaseSixSevenArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_six_cms_ecosystem_audit_passes(): void
    {
        $this->artisan('azari:cms-ecosystem-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 6 CMS, admin and user ecosystem audit passed.')
            ->assertSuccessful();
    }

    public function test_phase_seven_security_audit_passes(): void
    {
        $this->artisan('azari:security-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 7 application security audit passed.')
            ->assertSuccessful();
    }

    public function test_security_headers_are_applied_to_responses(): void
    {
        $middleware = new SecurityHeaders();
        $request = Request::create('/security-header-check', 'GET');

        $response = $middleware->handle(
            $request,
            static fn (): Response => new Response('ok')
        );

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    public function test_security_headers_middleware_is_registered_globally(): void
    {
        $this->assertStringContainsString(
            'SecurityHeaders::class',
            File::get(base_path('bootstrap/app.php'))
        );
    }

    public function test_post_forms_keep_csrf_protection(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $content = File::get($file->getPathname());

            if (preg_match('/<form\b/i', $content) &&
                preg_match('/method\s*=\s*["\']post["\']/i', $content) &&
                ! str_contains($content, '@csrf')) {
                $violations[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $violations, 'POST forms without @csrf: '.implode(', ', $violations));
    }
}
