<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deploy/uptime health check: confirms the database is reachable and — on
 * PostgreSQL — that the pgvector extension backing semantic search is
 * installed and the embedding columns are real vector columns.
 *
 * Registered outside the "web" middleware group (see bootstrap/app.php) so
 * platform health polls don't create a session row per hit.
 *
 * Contract: 200 with {"status":"ok"} when everything the current driver
 * needs is in place; 503 with {"status":"fail"} and per-check details
 * otherwise. On non-PostgreSQL drivers the pgvector check reports "skipped"
 * and does not affect the overall status — sqlite dev uses the in-memory
 * VectorSearch fallback and needs no extension.
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();
        $pgvector = $database['ok'] && $database['driver'] === 'pgsql'
            ? $this->checkPgvector()
            : ['status' => 'skipped', 'note' => 'not PostgreSQL; in-memory vector search fallback is used'];

        $healthy = $database['ok'] && ($pgvector['status'] ?? null) !== 'fail';

        return response()->json([
            'status' => $healthy ? 'ok' : 'fail',
            'checks' => [
                'database' => $database,
                'pgvector' => $pgvector,
            ],
        ], $healthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, driver: ?string, error?: string}
     */
    private function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $connection->select('select 1');

            return ['ok' => true, 'driver' => $connection->getDriverName()];
        } catch (Throwable $e) {
            return ['ok' => false, 'driver' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, extension_version?: ?string, vector_columns?: bool, error?: string}
     */
    private function checkPgvector(): array
    {
        try {
            $version = DB::selectOne(
                "select extversion from pg_extension where extname = 'vector'"
            )->extversion ?? null;

            // Both embedding columns must actually be vector-typed; a json
            // column here means the conversion migration never ran.
            $vectorColumns = (int) (DB::selectOne(
                "select count(*) as total from information_schema.columns
                 where table_name in ('job_listings', 'candidate_profiles')
                   and column_name = 'embedding'
                   and udt_name = 'vector'"
            )->total ?? 0);

            $ok = $version !== null && $vectorColumns === 2;

            return [
                'status' => $ok ? 'ok' : 'fail',
                'extension_version' => $version,
                'vector_columns' => $vectorColumns === 2,
            ];
        } catch (Throwable $e) {
            return ['status' => 'fail', 'error' => $e->getMessage()];
        }
    }
}
