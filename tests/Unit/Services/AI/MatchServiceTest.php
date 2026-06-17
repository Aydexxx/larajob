<?php

namespace Tests\Unit\Services\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class MatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the JobObserver's queued embedding job from running inline and
        // consuming AI calls; this test isolates MatchService's own calls.
        Queue::fake();
    }

    /**
     * Bind a fake provider instance (so call counters survive re-resolution)
     * and return the MatchService wired to it.
     */
    private function serviceWith(FakeAIProvider $fake): MatchService
    {
        $this->app->instance(AIProvider::class, $fake);

        return $this->app->make(MatchService::class);
    }

    private function profile(): CandidateProfile
    {
        return CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
        ]);
    }

    private function job(array $embedding): Job
    {
        return Job::factory()->active()->create([
            'embedding' => $embedding,
            'embedded_at' => now(),
        ]);
    }

    public function test_percentage_comes_from_embedding_cosine_similarity(): void
    {
        // Profile embeds to [1,0]; job vector [1,1] → cosine ≈ 0.7071 → 71%.
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"Solid fit","strengths":["PHP","Laravel"],"gaps":["Kubernetes"]}',
        );

        $result = $this->serviceWith($fake)->score($this->profile(), $this->job([1.0, 1.0]));

        $this->assertSame(71, $result->percentage);
        $this->assertSame('Solid fit', $result->summary);
        $this->assertSame(['PHP', 'Laravel'], $result->strengths);
        $this->assertSame(['Kubernetes'], $result->gaps);
    }

    public function test_aligned_vectors_produce_a_full_score(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"Great","strengths":[],"gaps":[]}',
        );

        $result = $this->serviceWith($fake)->score($this->profile(), $this->job([1.0, 0.0]));

        $this->assertSame(100, $result->percentage);
        $this->assertSame('high', $result->tier());
    }

    public function test_json_wrapped_in_prose_or_fences_is_still_parsed(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: "Sure! Here is the result:\n```json\n{\"summary\":\"Wrapped\",\"strengths\":[\"A\"],\"gaps\":[]}\n```\nHope that helps.",
        );

        $result = $this->serviceWith($fake)->score($this->profile(), $this->job([1.0, 0.0]));

        $this->assertSame('Wrapped', $result->summary);
        $this->assertSame(['A'], $result->strengths);
    }

    public function test_invalid_json_falls_back_to_embedding_only_result(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: 'I cannot produce JSON today, sorry.',
        );

        $result = $this->serviceWith($fake)->score($this->profile(), $this->job([1.0, 0.0]));

        // Score still derives from embeddings; narrative degrades gracefully.
        $this->assertSame(100, $result->percentage);
        $this->assertNotSame('', $result->summary);
        $this->assertSame([], $result->strengths);
        $this->assertSame([], $result->gaps);
    }

    public function test_a_cached_pair_is_not_recomputed(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"Cached","strengths":[],"gaps":[]}',
        );

        $service = $this->serviceWith($fake);
        $profile = $this->profile();
        $job = $this->job([1.0, 0.0]);

        $service->score($profile, $job);
        $embedAfterFirst = $fake->embedCalls;
        $chatAfterFirst = $fake->chatCalls;

        $service->score($profile, $job);

        $this->assertSame($embedAfterFirst, $fake->embedCalls, 'Embedding should not be recomputed for a cached pair.');
        $this->assertSame($chatAfterFirst, $fake->chatCalls, 'LLM should not be called again for a cached pair.');
    }

    public function test_stored_job_embedding_is_reused_instead_of_re_embedding_the_job(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"x","strengths":[],"gaps":[]}',
        );

        $this->serviceWith($fake)->score($this->profile(), $this->job([1.0, 0.0]));

        // Only the profile is embedded; the job reuses its stored vector.
        $this->assertSame(1, $fake->embedCalls);
    }

    public function test_cached_returns_null_until_computed_then_the_result(): void
    {
        $fake = new FakeAIProvider(
            enabled: true,
            vector: [1.0, 0.0],
            chatResponse: '{"summary":"y","strengths":[],"gaps":[]}',
        );

        $service = $this->serviceWith($fake);
        $profile = $this->profile();
        $job = $this->job([1.0, 0.0]);

        $this->assertNull($service->cached($profile, $job));

        $service->score($profile, $job);

        $this->assertNotNull($service->cached($profile, $job));
        $this->assertSame(0, $fake->embedCalls + $fake->chatCalls - 2, 'cached() must not trigger extra AI calls.');
    }

    public function test_profile_is_scorable_requires_skills_and_some_narrative(): void
    {
        $service = $this->serviceWith(new FakeAIProvider(enabled: true));

        $complete = new CandidateProfile(['headline' => 'Dev', 'skills' => 'PHP']);
        $noSkills = new CandidateProfile(['headline' => 'Dev', 'skills' => null]);
        $noNarrative = new CandidateProfile(['headline' => null, 'bio' => null, 'skills' => 'PHP']);
        $bioOnly = new CandidateProfile(['bio' => 'Experienced', 'skills' => 'PHP']);

        $this->assertTrue($service->profileIsScorable($complete));
        $this->assertTrue($service->profileIsScorable($bioOnly));
        $this->assertFalse($service->profileIsScorable($noSkills));
        $this->assertFalse($service->profileIsScorable($noNarrative));
        $this->assertFalse($service->profileIsScorable(null));
    }
}
