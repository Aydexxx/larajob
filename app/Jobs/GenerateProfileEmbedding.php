<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CandidateProfile;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates and stores the embedding vector for a single candidate profile.
 *
 * Always queued — never call AIService::embed() from the request cycle.
 * Quietly no-ops when the AI layer is disabled (AI_PROVIDER=none or
 * missing credentials), so dispatching this job is always safe regardless
 * of configuration. Mirrors {@see GenerateJobEmbedding} for the profile side
 * of semantic search.
 */
class GenerateProfileEmbedding implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $profileId,
    ) {}

    public function handle(AIProvider $ai): void
    {
        if (! $ai->isEnabled()) {
            // No error, no retry: re-embedding is simply queued again the
            // next time the profile is saved or backfilled once AI is enabled.
            return;
        }

        $profile = CandidateProfile::find($this->profileId);

        if (! $profile) {
            return;
        }

        $vector = $ai->embed($this->buildInput($profile), 'embedding');

        // saveQuietly: writing the embedding must not re-fire the
        // "updated" model event, or CandidateProfileObserver would dispatch
        // this same job again and loop forever.
        $profile->forceFill([
            'embedding' => $vector,
            'embedded_at' => now(),
        ])->saveQuietly();

        Log::channel('ai')->info('Profile embedding stored', [
            'profile_id' => $profile->id,
            'dimensions' => count($vector),
        ]);
    }

    /**
     * Build the text fed to the embedding model from the fields that
     * describe the candidate. Keep this in sync with
     * CandidateProfile::EMBEDDABLE_FIELDS, which drives re-embedding on
     * update.
     */
    private function buildInput(CandidateProfile $profile): string
    {
        return implode("\n\n", array_filter([
            $profile->headline,
            $profile->bio,
            filled($profile->skills) ? "Skills: {$profile->skills}" : null,
            $profile->experience_years !== null ? "Years of experience: {$profile->experience_years}" : null,
            $profile->location,
        ]));
    }
}
