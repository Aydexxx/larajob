<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\Contracts\VectorSearch as VectorSearchContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ranks job listings by embedding similarity for the public job board.
 *
 * Falls back cleanly to null/empty results whenever the AI layer is
 * disabled, so callers (PublicJobController) can drop straight back to
 * the existing keyword search with no special-casing of their own.
 */
class JobSearchService
{
    private const QUERY_EMBEDDING_TTL_MINUTES = 5;

    private const DEFAULT_SIMILAR_LIMIT = 4;

    public function __construct(
        private readonly AIProvider $ai,
        private readonly VectorSearchContract $vectorSearch,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    /**
     * Rank the given (already filtered) active-jobs query by semantic
     * similarity to $term. Returns null when AI is disabled so the caller
     * falls back to keyword search; never throws for that case.
     *
     * @return Collection<int, Job>|null
     */
    public function rankByQuery(string $term, Builder $query): ?Collection
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $queryVector = $this->embedQuery($term);

        // Only candidates already matching the structured filters (and
        // that have an embedding) are loaded — never the whole table.
        $candidates = (clone $query)->hasEmbedding()->with('company')->get();

        return $this->vectorSearch->search($queryVector, $candidates, $candidates->count());
    }

    /**
     * The top N active jobs most similar to the given job, excluding
     * itself. Empty when AI is disabled or the job has no embedding yet.
     *
     * @return Collection<int, Job>
     */
    public function similarTo(Job $job, int $limit = self::DEFAULT_SIMILAR_LIMIT): Collection
    {
        if (! $this->isAvailable() || empty($job->embedding)) {
            return new Collection;
        }

        $candidates = Job::active()
            ->whereKeyNot($job->id)
            ->hasEmbedding()
            ->with('company')
            ->get();

        return $this->vectorSearch->search($job->embedding, $candidates, $limit);
    }

    /**
     * @return array<int, float>
     */
    private function embedQuery(string $term): array
    {
        $cacheKey = 'ai:query-embedding:'.config('ai.embedding_model').':'.md5($term);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(self::QUERY_EMBEDDING_TTL_MINUTES),
            fn () => $this->ai->embed($term, 'embedding')
        );
    }
}
