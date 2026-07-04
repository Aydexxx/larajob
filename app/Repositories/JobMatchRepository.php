<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\Contracts\VectorSearch as VectorSearchContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Ranks active job listings against a candidate-profile embedding.
 *
 * On PostgreSQL the ranking happens in the database with pgvector's cosine
 * distance operator (<=>), ordered so the ivfflat index on
 * job_listings.embedding can serve the query, and only the top N rows are
 * ever hydrated. On other drivers (sqlite in tests) it degrades to the
 * existing in-memory {@see VectorSearchContract} ranking over the same
 * candidate set, so behaviour — including the similarity attribute — is
 * identical either way.
 */
class JobMatchRepository
{
    /**
     * How long a ranked feed stays warm before it's recomputed even if the
     * profile embedding hasn't changed. topMatches() has no per-job
     * invalidation signal the way a single (profile, job) match does — it
     * ranks across the *whole* active-jobs table, and a newly posted job
     * could outrank everything currently cached. A short TTL bounds that
     * staleness to a few minutes while still saving the ranking query on
     * every home-page view for the (by far) more common case of repeat
     * visits with an unchanged profile.
     */
    private const FEED_CACHE_TTL_MINUTES = 15;

    public function __construct(
        private readonly VectorSearchContract $vectorSearch,
    ) {}

    /**
     * The top-N active, embedded jobs most similar to the given profile
     * embedding, ordered by cosine similarity descending. Each returned Job
     * carries a float `similarity` attribute in [-1, 1] (raw cosine, where
     * 1.0 is an exact match).
     *
     * @param  array<int, float>  $profileEmbedding
     * @return Collection<int, Job>
     */
    public function topMatches(array $profileEmbedding, int $limit = 10): Collection
    {
        if ($profileEmbedding === []) {
            return new Collection;
        }

        $query = Job::query()->active()->hasEmbedding()->with('company');

        if ($query->getConnection()->getDriverName() !== 'pgsql') {
            return $this->vectorSearch->search($profileEmbedding, $query->get(), $limit);
        }

        // pgvector's input literal is the same "[f1,f2,...]" shape JSON uses.
        $vector = json_encode($profileEmbedding);

        return $query
            ->select('job_listings.*')
            ->selectRaw('1 - (embedding <=> ?::vector) AS similarity', [$vector])
            // Order by raw distance (not the aliased expression) so the
            // ivfflat cosine index is eligible to serve the scan.
            ->orderByRaw('embedding <=> ?::vector', [$vector])
            ->limit($limit)
            ->get()
            ->each(function (Job $job): void {
                $job->similarity = (float) $job->similarity;
            });
    }

    /**
     * The "For You" feed: topMatches(), cached per (profile embedding
     * version, limit). A warm profile costs no ranking query at all —
     * repeat home-page views are pure cache reads — and a re-embed (the
     * only thing that can change what "matches" means for this profile)
     * naturally busts the key by changing {@see CandidateProfile::embeddingVersion()}.
     *
     * Only the ordered job IDs are cached, not hydrated models: re-hydrating
     * on every read means a job that closed or was deleted after caching is
     * simply dropped from the feed rather than served stale, while still
     * avoiding the ranking query itself.
     *
     * @return Collection<int, Job>
     */
    public function cachedTopMatches(CandidateProfile $profile, int $limit = 10): Collection
    {
        $ids = Cache::remember(
            $this->feedCacheKey($profile, $limit),
            now()->addMinutes(self::FEED_CACHE_TTL_MINUTES),
            fn (): array => $this->topMatches($profile->embedding, $limit)->pluck('id')->all(),
        );

        if ($ids === []) {
            return new Collection;
        }

        $jobs = Job::query()->active()->hasEmbedding()->with('company')->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $jobs->get($id))
            ->filter()
            ->values();
    }

    private function feedCacheKey(CandidateProfile $profile, int $limit): string
    {
        return implode(':', [
            'ai:for-you-feed',
            config('ai.embedding_model'),
            $profile->id,
            $profile->embeddingVersion(),
            $limit,
        ]);
    }
}
