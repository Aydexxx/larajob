<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Prompts\CoverLetterDraftPrompt;
use RuntimeException;

/**
 * Generates an editable cover-letter draft for a candidate applying to a
 * job. The result is always a starting point: callers must present it as an
 * editable draft in the application form and never submit it automatically.
 */
class CoverLetterDraftService
{
    public function __construct(
        private readonly AIProvider $ai,
        private readonly CoverLetterDraftPrompt $prompt,
    ) {}

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

    public function draft(CandidateProfile $profile, Job $job): string
    {
        $raw = trim($this->ai->chat(
            $this->prompt->prompt($profile, $job),
            $this->prompt->system(),
        ));

        if ($raw === '') {
            throw new RuntimeException('AI cover letter draft was empty.');
        }

        return $raw;
    }
}
