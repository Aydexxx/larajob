<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills any `resume_analyzing` values that are still NULL to false.
 *
 * The column was introduced with `default(false)` and NOT NULL, so a
 * freshly-migrated database has no nulls and this is a no-op. It exists for
 * databases that acquired the column while it was (transiently) nullable, or
 * that carry rows written before the default applied — an in-flight resume
 * analysis is never persisted across deploys, so false is always the correct
 * resolved value. Idempotent and safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('candidate_profiles', 'resume_analyzing')) {
            return;
        }

        DB::table('candidate_profiles')
            ->whereNull('resume_analyzing')
            ->update(['resume_analyzing' => false]);
    }

    public function down(): void
    {
        // No-op: coercing nulls to false is not meaningfully reversible.
    }
};
