<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Application;
use App\Services\AI\MatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Warms the match-score cache for a freshly submitted application.
 *
 * Dispatched when a candidate applies, so that by the time an employer
 * opens the applications list the score is already cached and the list can
 * render (and sort) without any inline LLM call. Quietly no-ops when AI is
 * disabled or the candidate's profile isn't scorable yet — dispatching is
 * always safe regardless of configuration.
 */
class ComputeApplicationMatch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $applicationId,
    ) {}

    public function handle(MatchService $matches): void
    {
        if (! $matches->isAvailable()) {
            return;
        }

        $application = Application::with(['job', 'user.candidateProfile'])->find($this->applicationId);

        if (! $application || ! $application->job) {
            return;
        }

        $profile = $application->user?->candidateProfile;

        if (! $matches->profileIsScorable($profile)) {
            return;
        }

        // Computes and caches; the result itself is read later via cached().
        $matches->score($profile, $application->job);
    }
}
