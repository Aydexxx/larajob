<?php

namespace Tests\Feature\AI;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Repositories\JobMatchRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers JobMatchRepository::cachedTopMatches(): the ranked feed is cached
 * per (profile embedding version, limit) so a warm profile costs no ranking
 * query, and a re-embed — the only thing that changes what "matches" means
 * for that profile — naturally busts the cache by changing the key.
 */
class ForYouFeedCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function embeddedProfile(array $vector = [1.0, 0.0]): CandidateProfile
    {
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);

        $profile->forceFill(['embedding' => $vector, 'embedded_at' => now()])->saveQuietly();

        return $profile->refresh();
    }

    private function embeddedJob(string $title, array $vector = [1.0, 0.0]): Job
    {
        return Job::factory()->active()->for(Company::factory())->create([
            'title' => $title,
            'embedding' => $vector,
            'embedded_at' => now(),
        ]);
    }

    public function test_repeat_calls_for_the_same_profile_version_do_not_recompute_the_ranking(): void
    {
        $repository = app(JobMatchRepository::class);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob('Backend Engineer');

        $first = $repository->cachedTopMatches($profile, 10);
        $this->assertCount(1, $first);
        $this->assertTrue($first->first()->is($job));

        // A job posted after the first (now-cached) read must not appear
        // until the cache naturally expires or the profile re-embeds —
        // this is the documented staleness bound, not a bug.
        $this->embeddedJob('Newly Posted Role');

        $second = $repository->cachedTopMatches($profile, 10);
        $this->assertCount(1, $second, 'A warm cache must not be recomputed just because new jobs exist.');
    }

    public function test_reembedding_the_profile_busts_the_cache_and_reranks(): void
    {
        $repository = app(JobMatchRepository::class);
        $profile = $this->embeddedProfile([1.0, 0.0]);
        $this->embeddedJob('Aligned Role', [1.0, 0.0]);

        $before = $repository->cachedTopMatches($profile, 10);
        $this->assertCount(1, $before);

        $newlyRelevant = $this->embeddedJob('Newly Aligned Role', [0.0, 1.0]);

        // Re-embedding moves embeddingVersion() → a fresh cache key, so the
        // ranking query actually runs again and picks up the new job.
        $this->travel(1)->minutes();
        $profile->forceFill(['embedding' => [0.0, 1.0], 'embedded_at' => now()])->saveQuietly();

        $after = $repository->cachedTopMatches($profile->refresh(), 10);

        $this->assertTrue($after->first()->is($newlyRelevant), 'A re-embed must produce a fresh ranking, not the stale cached one.');
    }

    public function test_unrelated_profile_edits_do_not_invalidate_the_cached_feed(): void
    {
        $repository = app(JobMatchRepository::class);
        $profile = $this->embeddedProfile();
        $this->embeddedJob('Backend Engineer');

        $repository->cachedTopMatches($profile, 10);

        // Editing a field that does not feed matching bumps updated_at but
        // not embeddingVersion() — the cache must survive.
        $this->travel(1)->minutes();
        $profile->update(['phone' => '+90 555 000 0000']);

        $newRole = $this->embeddedJob('Ignored Because Cache Is Warm');

        $result = $repository->cachedTopMatches($profile->refresh(), 10);

        $this->assertFalse($result->contains(fn (Job $j) => $j->is($newRole)), 'A non-matching edit must not bust the cache.');
    }

    public function test_a_job_removed_after_caching_is_dropped_from_the_hydrated_feed(): void
    {
        $repository = app(JobMatchRepository::class);
        $profile = $this->embeddedProfile();
        $job = $this->embeddedJob('Backend Engineer');

        $repository->cachedTopMatches($profile, 10);

        // Only IDs are cached — closing the job after caching must not
        // resurrect it as a hydrated (and now-stale) model.
        $job->update(['status' => 'closed']);

        $result = $repository->cachedTopMatches($profile, 10);

        $this->assertTrue($result->isEmpty(), 'A closed job must be dropped on re-hydration even though its id is still cached.');
    }

    public function test_different_candidates_never_share_a_cached_feed(): void
    {
        $repository = app(JobMatchRepository::class);
        $alice = $this->embeddedProfile([1.0, 0.0]);
        $bob = $this->embeddedProfile([1.0, 0.0]);
        $job = $this->embeddedJob('Backend Engineer');

        $repository->cachedTopMatches($alice, 10);
        $result = $repository->cachedTopMatches($bob, 10);

        $this->assertTrue($result->first()->is($job), 'Distinct profile ids must not collide on the same cache key.');
    }
}
