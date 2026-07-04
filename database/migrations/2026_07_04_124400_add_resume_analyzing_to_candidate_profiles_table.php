<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks whether a dispatched ParseResume job is still running for the
 * profile's current resume, so the UI can honestly show "analyzing" while
 * the queue works and resolve into the review screen once it's done —
 * rather than inferring progress from timestamps.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->boolean('resume_analyzing')->default(false)->after('suggested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('resume_analyzing');
        });
    }
};
