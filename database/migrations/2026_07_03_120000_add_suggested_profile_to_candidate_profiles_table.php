<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the CV-parsing output as a pending suggestion. Parsed resume data is
 * NEVER written to the live profile fields directly — it sits here until the
 * candidate reviews and explicitly applies it (see
 * CandidateResumeSuggestionController), then the columns are cleared.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->json('suggested_profile')->nullable();
            $table->timestamp('suggested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['suggested_profile', 'suggested_at']);
        });
    }
};
