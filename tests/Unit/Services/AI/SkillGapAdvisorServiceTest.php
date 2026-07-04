<?php

namespace Tests\Unit\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\SkillGapAdvisorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

/**
 * Covers SkillGapAdvisorService: a fully rule-based (no LLM, ever) aggregate
 * over the candidate's near-miss jobs — roles the embedding ranks as a
 * meaningful fit but not yet a strong one — surfacing which skills, missing
 * from the candidate's profile but present in those postings, show up most.
 */
class SkillGapAdvisorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function enableAi(): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(enabled: true));
    }

    private function service(): SkillGapAdvisorService
    {
        return $this->app->make(SkillGapAdvisorService::class);
    }

    private function embeddedProfile(array $vector, array $overrides = []): CandidateProfile
    {
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create(array_merge([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ], $overrides));

        $profile->forceFill(['embedding' => $vector, 'embedded_at' => now()])->saveQuietly();

        return $profile->refresh();
    }

    /** A unit vector at cosine $similarity from [1, 0], used to place a job at an exact score. */
    private function vectorAtSimilarity(float $similarity): array
    {
        return [$similarity, sqrt(max(0.0, 1 - $similarity ** 2))];
    }

    private function jobAt(float $similarity, string $requirements, string $title = 'Role'): Job
    {
        $job = Job::factory()->active()->for(Company::factory())->create([
            'title' => $title,
            'description' => 'Build great things.',
            'requirements' => $requirements,
        ]);

        $job->forceFill(['embedding' => $this->vectorAtSimilarity($similarity), 'embedded_at' => now()])->saveQuietly();

        return $job->refresh();
    }

    /** Seeds the platform-wide skill vocabulary with skills not on the profile under test. */
    private function seedVocabulary(string $skills): void
    {
        CandidateProfile::factory()->for(User::factory()->candidate())->create(['skills' => $skills]);
    }

    public function test_returns_nothing_when_ai_is_disabled(): void
    {
        $profile = $this->embeddedProfile([1.0, 0.0]);
        $this->jobAt(0.6, 'Requires AWS experience.');

        $this->assertSame([], $this->service()->recommendationsFor($profile));
    }

    public function test_returns_nothing_when_the_profile_is_not_scorable(): void
    {
        $this->enableAi();
        $profile = $this->embeddedProfile([1.0, 0.0], ['skills' => null, 'headline' => null, 'bio' => null]);

        $this->assertSame([], $this->service()->recommendationsFor($profile));
    }

    public function test_returns_nothing_when_the_profile_has_no_embedding(): void
    {
        $this->enableAi();
        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);

        $this->assertSame([], $this->service()->recommendationsFor($profile));
    }

    public function test_recommends_the_skill_appearing_in_the_most_near_miss_jobs(): void
    {
        $this->enableAi();
        $this->seedVocabulary('AWS, Docker, Kubernetes');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        // All three land in the medium band (score 50-74).
        $this->jobAt(0.6, 'Strong AWS and Docker skills required.', 'Job A');
        $this->jobAt(0.55, 'AWS experience is a must.', 'Job B');
        $this->jobAt(0.65, 'Some Docker knowledge helpful.', 'Job C');

        $result = $this->service()->recommendationsFor($profile);

        $bySkill = collect($result)->keyBy('skill');
        $this->assertSame(2, $bySkill['AWS']['jobCount']);
        $this->assertSame(2, $bySkill['Docker']['jobCount']);
        $this->assertArrayNotHasKey('Kubernetes', $bySkill->all(), 'Kubernetes was never mentioned in any posting.');
    }

    public function test_excludes_skills_the_candidate_already_has(): void
    {
        $this->enableAi();
        $this->seedVocabulary('PHP, Kubernetes');

        $profile = $this->embeddedProfile([1.0, 0.0], ['skills' => 'PHP, Laravel']);
        $this->jobAt(0.6, 'Requires PHP and Kubernetes experience.');

        $result = $this->service()->recommendationsFor($profile);

        $skills = collect($result)->pluck('skill')->all();
        $this->assertNotContains('PHP', $skills, 'A skill the candidate already has is not a gap.');
        $this->assertContains('Kubernetes', $skills);
    }

    public function test_ignores_jobs_that_are_already_a_strong_match(): void
    {
        $this->enableAi();
        $this->seedVocabulary('Rust');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        // High tier (>=75) — already a strong match, nothing to unlock.
        $this->jobAt(0.9, 'Requires Rust experience.');

        $this->assertSame([], $this->service()->recommendationsFor($profile));
    }

    public function test_ignores_jobs_that_are_a_weak_match(): void
    {
        $this->enableAi();
        $this->seedVocabulary('Rust');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        // Low tier (<50) — too far off for one skill to plausibly close the gap.
        $this->jobAt(0.2, 'Requires Rust experience.');

        $this->assertSame([], $this->service()->recommendationsFor($profile));
    }

    public function test_word_boundary_matching_avoids_substring_false_positives(): void
    {
        $this->enableAi();
        $this->seedVocabulary('Go, .NET');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        // "Go" must not match inside "Google"; ".NET" must match as its own
        // token despite starting with a non-alphanumeric character, and must
        // not match inside "internet".
        $this->jobAt(0.6, 'Experience with Google Cloud Platform and the internet is a plus.', 'False positive trap');
        $this->jobAt(0.55, 'Experience with .NET Core required.', 'Real .NET role');

        $result = $this->service()->recommendationsFor($profile);
        $skills = collect($result)->pluck('skill')->all();

        $this->assertNotContains('Go', $skills, '"Go" must not match as a substring of "Google".');
        $this->assertContains('.NET', $skills, '".NET" must match as a whole token even though it starts with a symbol.');
    }

    public function test_result_is_capped_and_sorted_descending_by_job_count(): void
    {
        $this->enableAi();
        $this->seedVocabulary('Terraform, Ansible, Kafka, GraphQL, Redis, Elasticsearch');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        $this->jobAt(0.6, 'Terraform required.', 'A');
        $this->jobAt(0.55, 'Terraform and Ansible required.', 'B');
        $this->jobAt(0.65, 'Terraform, Ansible, and Kafka required.', 'C');
        $this->jobAt(0.7, 'Terraform, Ansible, Kafka, and GraphQL required.', 'D');
        $this->jobAt(0.52, 'Terraform, Ansible, Kafka, GraphQL, and Redis required.', 'E');
        $this->jobAt(0.58, 'Terraform, Ansible, Kafka, GraphQL, Redis, and Elasticsearch required.', 'F');

        $result = $this->service()->recommendationsFor($profile);

        $this->assertCount(5, $result, 'At most 5 recommendations, even with 6 candidate skills.');
        $this->assertSame('Terraform', $result[0]['skill'], 'Terraform appears in all 6 near-misses — the clear leader.');
        $this->assertSame(6, $result[0]['jobCount']);
        // Strictly descending.
        for ($i = 1; $i < count($result); $i++) {
            $this->assertLessThanOrEqual($result[$i - 1]['jobCount'], $result[$i]['jobCount']);
        }
    }

    public function test_result_is_cached_until_the_profile_re_embeds(): void
    {
        $this->enableAi();
        // Both skills seeded into the vocabulary up front — that cache has
        // its own, much longer TTL and is not what this test is about.
        $this->seedVocabulary('AWS, Kubernetes');

        $profile = $this->embeddedProfile([1.0, 0.0]);
        $this->jobAt(0.6, 'Requires AWS experience.');

        $first = $this->service()->recommendationsFor($profile);
        $this->assertNotEmpty($first);

        // A job posted after the first (now-cached) read must not appear
        // until the profile re-embeds — same documented staleness bound as
        // the underlying near-miss feed cache.
        $this->jobAt(0.6, 'Requires Kubernetes experience.', 'New Role');

        $stillWarm = $this->service()->recommendationsFor($profile);
        $this->assertSame($first, $stillWarm, 'A warm cache must not be recomputed just because new data exists.');

        $this->travel(1)->minutes();
        $profile->forceFill(['embedding' => [1.0, 0.0], 'embedded_at' => now()])->saveQuietly();

        $afterReembed = $this->service()->recommendationsFor($profile->refresh());
        $skills = collect($afterReembed)->pluck('skill')->all();
        $this->assertContains('Kubernetes', $skills, 'A re-embed must produce a fresh computation.');
    }
}
