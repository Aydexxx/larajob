<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates and stores the embedding vector for a single job listing.
 *
 * Always queued — never call AIService::embed() from the request cycle.
 * Quietly no-ops when the AI layer is disabled (AI_PROVIDER=none or
 * missing credentials), so dispatching this job is always safe regardless
 * of configuration.
 */
class GenerateJobEmbedding implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $jobId,
    ) {}

    public function handle(AIProvider $ai): void
    {
        if (! $ai->isEnabled()) {
            // No error, no retry: re-embedding is simply queued again the
            // next time the job is saved or backfilled once AI is enabled.
            return;
        }

        $job = Job::find($this->jobId);

        if (! $job) {
            return;
        }

        $vector = $ai->embed($this->buildInput($job), 'embedding');

        // saveQuietly: writing the embedding must not re-fire the
        // "updated" model event, or JobObserver would dispatch this same
        // job again and loop forever.
        $job->forceFill([
            'embedding' => $vector,
            'embedded_at' => now(),
        ])->saveQuietly();

        Log::channel('ai')->info('Job embedding stored', [
            'job_id' => $job->id,
            'dimensions' => count($vector),
        ]);
    }

    /**
     * Build the text fed to the embedding model from the fields that
     * describe what the job actually is. Keep this in sync with
     * Job::EMBEDDABLE_FIELDS, which drives re-embedding on update.
     */
    private function buildInput(Job $job): string
    {
        return implode("\n\n", array_filter([
            $job->title,
            $job->description,
            $job->requirements,
            $job->location,
            $job->type,
        ]));
    }
}
