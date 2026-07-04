<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approximate-nearest-neighbour indexes for cosine-distance queries
 * (ORDER BY embedding <=> :query). ivfflat with lists = 100 is the pgvector
 * default guidance for tables up to ~1M rows; rebuild with more lists if the
 * job board grows past that. PostgreSQL only — the sqlite test path ranks
 * in memory and needs no index.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            'CREATE INDEX IF NOT EXISTS job_listings_embedding_cosine_index
             ON job_listings USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)'
        );
        DB::statement(
            'CREATE INDEX IF NOT EXISTS candidate_profiles_embedding_cosine_index
             ON candidate_profiles USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS job_listings_embedding_cosine_index');
        DB::statement('DROP INDEX IF EXISTS candidate_profiles_embedding_cosine_index');
    }
};
