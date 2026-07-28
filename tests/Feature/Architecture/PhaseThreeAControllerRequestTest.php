<?php

namespace Tests\Feature\Architecture;

use App\Http\Requests\AzariFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

class PhaseThreeAControllerRequestTest extends TestCase
{
    public function test_controller_request_audit_passes(): void
    {
        $this->artisan('azari:controller-audit', ['--strict' => true])
            ->expectsOutputToContain('Phase 3A controller and request audit passed.')
            ->assertSuccessful();
    }

    public function test_custom_form_requests_use_the_canonical_azari_base(): void
    {
        $requestDirectory = app_path('Http/Requests');

        if (! File::isDirectory($requestDirectory)) {
            $this->markTestSkipped('No custom Form Requests exist.');
        }

        $requestFiles = collect(File::allFiles($requestDirectory))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->reject(fn ($file) => $file->getFilename() === 'AzariFormRequest.php');

        foreach ($requestFiles as $file) {
            $content = $file->getContents();

            if (! preg_match('/namespace\s+([^;]+);.*?class\s+(\w+)/s', $content, $matches)) {
                continue;
            }

            $class = $matches[1].'\\'.$matches[2];

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isSubclassOf(FormRequest::class)) {
                continue;
            }

            $this->assertTrue(
                $reflection->isSubclassOf(AzariFormRequest::class),
                "{$class} must extend the canonical AzariFormRequest."
            );
        }

        $this->assertTrue(true);
    }

    public function test_controllers_do_not_read_unfiltered_or_superglobal_payloads(): void
    {
        $violations = [];

        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = $file->getContents();

            if (preg_match('/\$request->all\s*\(\s*\)|\$_(?:GET|POST|REQUEST|FILES)\b|\bextract\s*\(/', $content)) {
                $violations[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $violations, 'Unsafe controller payload access found.');
    }

    public function test_json_validation_failures_use_a_stable_safe_shape(): void
    {
        $request = new class extends AzariFormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return ['name' => ['required', 'string']];
            }
        };

        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->initialize([], [], [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
        ]);

        try {
            $request->validateResolved();
            $this->fail('Validation should have failed.');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            $response = $exception->getResponse();

            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame(
                'Please review the highlighted fields.',
                $response->getData(true)['message']
            );
            $this->assertArrayHasKey('name', $response->getData(true)['errors']);
        }
    }
}
