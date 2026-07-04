<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enables the pgvector extension that backs semantic search. Only runs on
 * PostgreSQL; on other drivers (sqlite in tests) this is a clean no-op and
 * embeddings stay in JSON columns with in-memory ranking as the fallback.
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

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left in place: other databases on the same server
        // may use the extension, and dropping it would destroy any vector
        // columns that still reference it.
    }
};
