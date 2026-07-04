<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateProfileEmbedding;
use App\Models\CandidateProfile;

/**
 * Keeps profile embeddings in sync by queuing GenerateProfileEmbedding
 * whenever a profile is created or one of its embeddable fields changes.
 * Registered on the model via #[ObservedBy(CandidateProfileObserver::class)].
 * Mirrors {@see JobObserver}.
 */
class CandidateProfileObserver
{
    public function created(CandidateProfile $profile): void
    {
        GenerateProfileEmbedding::dispatch($profile->id);
    }

    public function updated(CandidateProfile $profile): void
    {
        if ($profile->wasChanged(CandidateProfile::EMBEDDABLE_FIELDS)) {
            GenerateProfileEmbedding::dispatch($profile->id);
        }
    }
}
