<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves embeddings to real pgvector columns on PostgreSQL and adds embedding
 * storage to candidate profiles.
 *
 * On pgsql the existing json column on job_listings is dropped and recreated
 * as vector(1536) rather than cast in place: previously stored vectors may
 * not match the new fixed dimension, and embeddings are cheap to regenerate
 * (php artisan jobs:embed). embedded_at is reset so the backfill re-embeds
 * every job.
 *
 * On other drivers (sqlite in tests) embeddings remain JSON, which the
 * "array" model cast and the in-memory VectorSearch fallback already handle.
 *
 * 1536 = text-embedding-3-small; keep in sync with ai.embedding_dimensions.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE job_listings DROP COLUMN embedding');
            DB::statement('ALTER TABLE job_listings ADD COLUMN embedding vector(1536)');
            DB::statement('UPDATE job_listings SET embedded_at = NULL');

            DB::statement('ALTER TABLE candidate_profiles ADD COLUMN embedding vector(1536)');
        } else {
            Schema::table('candidate_profiles', function (Blueprint $table) {
                $table->json('embedding')->nullable();
            });
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->timestamp('embedded_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE job_listings DROP COLUMN embedding');
            DB::statement('ALTER TABLE job_listings ADD COLUMN embedding json');
            DB::statement('UPDATE job_listings SET embedded_at = NULL');
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedded_at']);
        });
    }
};
