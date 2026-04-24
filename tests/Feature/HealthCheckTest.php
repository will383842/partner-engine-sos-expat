<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_basic_health_endpoint_is_accessible(): void
    {
        $response = $this->get('/api/health');
        $response->assertJsonStructure(['status', 'version', 'database', 'redis', 'timestamp']);
    }

    public function test_detailed_health_endpoint_returns_components(): void
    {
        $response = $this->get('/api/health/detailed');
        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'components' => [
                'database',
                'redis',
                'config',
            ],
        ]);
    }

    public function test_detailed_health_includes_config_flags(): void
    {
        $response = $this->get('/api/health/detailed');
        $response->assertJsonStructure([
            'components' => [
                'config' => [
                    'app_env',
                    'app_key_set',
                    'stripe_webhook_secret_set',
                    'engine_api_key_set',
                ],
            ],
        ]);
    }
}
