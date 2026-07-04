<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The /health endpoint backs the Railway deploy health check: it must
 * report the database connection on every driver. The suite normally runs
 * on sqlite, where the pgvector check reports "skipped" (in-memory
 * vector-search fallback); when pointed at PostgreSQL it must report "ok",
 * meaning the extension and vector columns from the migrations are in
 * place. Assertions are driver-aware so this test holds on both.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_reports_ok_and_the_driver_appropriate_pgvector_status(): void
    {
        $driver = DB::connection()->getDriverName();

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.database.driver', $driver)
            ->assertJsonPath('checks.pgvector.status', $driver === 'pgsql' ? 'ok' : 'skipped');
    }

    public function test_health_endpoint_is_publicly_reachable_without_a_session(): void
    {
        $this->getJson('/health')->assertOk();

        // Registered outside the "web" group: a health poll must not have
        // started a session (Railway polls this endpoint continuously).
        $this->assertFalse(app('request')->hasSession());
    }
}
