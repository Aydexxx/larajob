<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Job;

/**
 * Keeps job embeddings in sync by queuing GenerateJobEmbedding whenever a
 * job is created or one of its embeddable fields changes. Registered on
 * the model via #[ObservedBy(JobObserver::class)].
 */
class JobObserver
{
    public function created(Job $job): void
    {
        GenerateJobEmbedding::dispatch($job->id);
    }

    public function updated(Job $job): void
    {
        if ($job->wasChanged(Job::EMBEDDABLE_FIELDS)) {
            GenerateJobEmbedding::dispatch($job->id);
        }
    }
}
