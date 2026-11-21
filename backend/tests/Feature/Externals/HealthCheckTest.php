<?php

namespace Tests\Feature\Externals;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_checks_and_echoes_correlation_ids(): void
    {
        $response = $this->withHeaders([
            'X-Correlation-Id' => 'health-correlation',
            'X-Request-Id' => 'health-request',
        ])->getJson('/api/health');

        $response->assertOk()
            ->assertHeader('X-Correlation-Id', 'health-correlation')
            ->assertHeader('X-Request-Id', 'health-request')
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.queue.ok', true)
            ->assertJsonPath('failed_jobs.count', 0)
            ->assertJsonPath('correlation_id', 'health-correlation');
        $this->assertIsInt($response->json('checks.queue.pending'));
    }

    public function test_health_endpoint_returns_503_when_the_database_is_unavailable(): void
    {
        $originalConnection = config('database.default');
        config(['database.default' => 'stage84_missing_connection']);

        try {
            $this->getJson('/api/health')
                ->assertServiceUnavailable()
                ->assertJsonPath('status', 'degraded')
                ->assertJsonPath('checks.database.ok', false)
                ->assertJsonPath('failed_jobs.count', null);
        } finally {
            config(['database.default' => $originalConnection]);
        }
    }
}
