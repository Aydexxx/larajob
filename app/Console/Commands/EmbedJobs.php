<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateJobEmbedding;
use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:embed {--force : Re-embed every job, including ones that already have an embedding}')]
#[Description('Backfill embeddings for job listings by queuing GenerateJobEmbedding for each')]
class EmbedJobs extends Command
{
    public function handle(AIProvider $ai): int
    {
        if (! $ai->isEnabled()) {
            $this->warn('AI layer is disabled (AI_PROVIDER=none or missing credentials). Nothing to embed.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        $query = Job::query();

        if (! $force) {
            $query->whereNull('embedded_at');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info($force
                ? 'No jobs found.'
                : 'All jobs already have embeddings. Use --force to re-embed everything.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Queuing embeddings for %d job(s)...', $total));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select('id')->chunkById(100, function ($jobs) use ($bar) {
            foreach ($jobs as $job) {
                GenerateJobEmbedding::dispatch($job->id);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Queued %d embedding job(s). Run `php artisan queue:work` to process them.',
            $total
        ));

        return self::SUCCESS;
    }
}
