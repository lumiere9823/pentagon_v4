<?php

namespace Tests\Feature\Production;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_laravel_health_up_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    public function test_production_health_check_command_succeeds_with_zero_exit_code(): void
    {
        $exitCode = Artisan::call('app:production-health-check');

        $this->assertSame(0, $exitCode);
    }

    public function test_production_health_check_command_json_output_is_valid_and_healthy(): void
    {
        Artisan::call('app:production-health-check', ['--json' => true]);
        $output = Artisan::output();

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertTrue($data['healthy']);
        $this->assertSame('OK', $data['checks']['database']['status']);
        $this->assertSame('OK', $data['checks']['migrations']['status']);
        $this->assertSame('OK', $data['checks']['storage_permissions']['status']);
        $this->assertSame('OK', $data['checks']['cache']['status']);
    }

    public function test_production_mode_does_not_leak_stack_traces_on_not_found(): void
    {
        config(['app.debug' => false, 'app.env' => 'production']);

        $response = $this->get('/non-existent-probe-route-12345');

        $response->assertNotFound();
        $content = $response->getContent() ?: '';
        $this->assertStringNotContainsString('Stack trace:', $content);
        $this->assertStringNotContainsString('Illuminate\\Routing', $content);
        $this->assertStringNotContainsString(base_path(), $content);
    }

    public function test_critical_storage_directories_are_writable_and_functional(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('smoke_test.txt', 'pentagon_production_smoke_test');
        $this->assertTrue(Storage::disk('local')->exists('smoke_test.txt'));
        $this->assertSame('pentagon_production_smoke_test', Storage::disk('local')->get('smoke_test.txt'));
        Storage::disk('local')->delete('smoke_test.txt');
        $this->assertFalse(Storage::disk('local')->exists('smoke_test.txt'));
    }

    public function test_public_robots_and_sitemap_endpoints_serve_cleanly_without_secrets(): void
    {
        $website = Website::factory()->create(['slug' => 'smoke-agency']);
        Page::factory()->create([
            'website_id' => $website->id,
            'slug' => 'smoke-home',
        ]);

        $robotsResponse = $this->get('/sites/smoke-agency/robots.txt');
        $robotsResponse->assertOk();
        $this->assertStringContainsString('Sitemap: http://localhost:8000/sites/smoke-agency/sitemap.xml', $robotsResponse->getContent() ?: '');
        $this->assertStringNotContainsString('password', $robotsResponse->getContent() ?: '');
        $this->assertStringNotContainsString('secret', $robotsResponse->getContent() ?: '');

        $sitemapResponse = $this->get('/sites/smoke-agency/sitemap.xml');
        $sitemapResponse->assertOk();
        $sitemapResponse->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringNotContainsString('password', $sitemapResponse->getContent() ?: '');
    }
}
