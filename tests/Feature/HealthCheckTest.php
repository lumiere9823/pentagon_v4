<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson(route('api.health'));

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => [
                    'database',
                ],
            ])
            ->assertJson([
                'status' => 'healthy',
                'services' => [
                    'database' => 'connected',
                ],
            ]);
    }

    public function test_health_check_does_not_leak_sensitive_internal_details(): void
    {
        $response = $this->getJson(route('api.health'));

        $payload = $response->json();

        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('database_password', $payload);
        $this->assertArrayNotHasKey('credentials', $payload);
        $this->assertArrayNotHasKey('trace', $payload);
        $this->assertArrayNotHasKey('sql', $payload);
        $this->assertArrayNotHasKey('memory', $payload);
    }

    public function test_system_up_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }
}
