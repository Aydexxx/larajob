<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateJobEmbedding;
use App\Jobs\GenerateProfileEmbedding;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * One-time backfill for both embedding tracks (job listings + candidate
 * profiles) after the AI layer is enabled.
 *
 * Dispatch always happens, regardless of whether AI is currently enabled:
 * queuing is cheap and harmless, and the queued jobs themselves
 * ({@see GenerateJobEmbedding}, {@see GenerateProfileEmbedding}) already
 * no-op safely when AI is disabled. That means this command is safe to run
 * at any time — including right now, with AI_PROVIDER=none — and the
 * embeddings will simply materialize once a worker processes the queue
 * after a real provider is configured.
 */
#[Signature('larajob:backfill-embeddings {--force : Re-embed every record, including ones that already have an embedding}')]
#[Description('Backfill embeddings for all job listings and candidate profiles by queuing the generation job for each')]
class BackfillEmbeddings extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $jobsQueued = $this->queueJobs($force);
        $profilesQueued = $this->queueProfiles($force);

        $this->info(sprintf(
            'Queued %d job listing(s) and %d candidate profile(s) for embedding.',
            $jobsQueued,
            $profilesQueued
        ));

        return self::SUCCESS;
    }

    private function queueJobs(bool $force): int
    {
        $query = Job::query();

        if (! $force) {
            $query->whereNull('embedded_at');
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $this->info(sprintf('Queuing embeddings for %d job listing(s)...', $total));

        $query->select('id')->chunkById(100, function ($jobs): void {
            foreach ($jobs as $job) {
                GenerateJobEmbedding::dispatch($job->id);
            }
        });

        return $total;
    }

    private function queueProfiles(bool $force): int
    {
        $query = CandidateProfile::query();

        if (! $force) {
            $query->whereNull('embedded_at');
        }

        $total = $query->count();

        if ($total === 0) {
            return 0;
        }

        $this->info(sprintf('Queuing embeddings for %d candidate profile(s)...', $total));

        $query->select('id')->chunkById(100, function ($profiles): void {
            foreach ($profiles as $profile) {
                GenerateProfileEmbedding::dispatch($profile->id);
            }
        });

        return $total;
    }
}
