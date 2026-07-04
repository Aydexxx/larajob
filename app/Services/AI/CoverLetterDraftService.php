<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\CoverLetterDraftPrompt;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Generates an editable cover-letter draft for a candidate applying to a
 * job. The result is always a starting point: callers must present it as an
 * editable draft in the application form and never submit it automatically.
 */
class CoverLetterDraftService
{
    /**
     * A generated draft stays warm for a day, keyed by (profile embedding
     * version, job version). The version signal means re-drafting the same
     * pair returns the cached text for free — no repeat API call, no cap
     * hit — while editing the profile fields that feed the letter
     * (headline/bio/skills/experience/location, i.e. EMBEDDABLE_FIELDS)
     * produces a fresh draft. See {@see CandidateProfile::embeddingVersion()}.
     */
    private const CACHE_TTL_MINUTES = 1440;

    public function __construct(
        private readonly AIProvider $ai,
        private readonly CoverLetterDraftPrompt $prompt,
        private readonly AICostGuard $guard,
    ) {}

    /**
     * Whether the signed-in actor is still within their daily cover-letter
     * cap. The controller checks this before drafting so an over-cap request
     * degrades to "write it yourself" instead of spending another call.
     */
    public function withinDailyCap(): bool
    {
        return $this->guard->allow('cover-letter');
    }

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * A profile needs at least one piece of substance for the draft to be
     * more than generic filler.
     */
    public function profileIsUsable(?CandidateProfile $profile): bool
    {
        return $profile !== null
            && (filled($profile->headline) || filled($profile->bio) || filled($profile->skills));
    }

    /**
     * A previously generated draft for this (profile version, job) pair, or
     * null when none is cached. Read-only: never calls the provider and never
     * touches the daily cap, so the controller can serve a warm draft for
     * free — even to a candidate who has hit their cap.
     */
    public function cachedDraft(CandidateProfile $profile, Job $job): ?string
    {
        $cached = Cache::get($this->cacheKey($profile, $job));

        return is_string($cached) ? $cached : null;
    }

    public function draft(CandidateProfile $profile, Job $job): string
    {
        return Cache::remember(
            $this->cacheKey($profile, $job),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($profile, $job): string {
                $raw = trim($this->ai->chat(
                    $this->prompt->prompt($profile, $job),
                    $this->prompt->system(),
                    'cover-letter',
                ));

                // The call was made — charge it to the actor's daily cap.
                // Only ever runs on a cache miss (real generation).
                $this->guard->hit('cover-letter');

                if ($raw === '') {
                    throw new RuntimeException('AI cover letter draft was empty.');
                }

                return $raw;
            },
        );
    }

    private function cacheKey(CandidateProfile $profile, Job $job): string
    {
        return implode(':', [
            'ai:cover-letter',
            config('ai.chat_model'),
            $profile->id,
            $profile->embeddingVersion(),
            $job->id,
            optional($job->updated_at)->timestamp ?? 0,
        ]);
    }
}
