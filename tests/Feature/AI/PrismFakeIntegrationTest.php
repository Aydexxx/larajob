<?php

namespace Tests\Feature\AI;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\AIService;
use App\Services\AI\MatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\EmbeddingsResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Embedding;
use Prism\Prism\ValueObjects\EmbeddingsUsage;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Tests\TestCase;

/**
 * Exercises the REAL AIService against Prism's own fake provider.
 *
 * Every other AI test swaps the whole AIProvider for a hand-rolled double
 * (Tests\Support\Doubles\FakeAIProvider), which never touches Prism. These
 * tests instead fake Prism itself (Prism::fake()), so the actual
 * AIService -> Prism -> provider wiring is covered end to end with ZERO
 * real HTTP calls: the configured provider/model is asserted on the
 * recorded request, and Prism::fake() guarantees nothing leaves the box.
 */
class PrismFakeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // Pretend OpenAI is configured and enabled. config/ai.php normally
        // computes these from env; we set them directly so AIService resolves
        // a real Prism provider enum without needing any credentials — the
        // request never leaves the fake.
        config()->set('ai.provider', 'openai');
        config()->set('ai.enabled', true);
        config()->set('ai.embedding_model', 'text-embedding-3-small');
        config()->set('ai.chat_model', 'gpt-4o-mini');
    }

    public function test_embed_returns_the_vector_from_prism_with_no_real_call(): void
    {
        $fake = Prism::fake([
            EmbeddingsResponseFake::make()
                ->withEmbeddings([Embedding::fromArray([0.11, 0.22, 0.33])])
                ->withUsage(new EmbeddingsUsage(7))
                ->withMeta(new Meta('fake-emb', 'fake-model')),
        ]);

        $vector = $this->app->make(AIService::class)->embed('A senior PHP engineer who loves Laravel.');

        $this->assertSame([0.11, 0.22, 0.33], $vector);

        $fake->assertCallCount(1);
        $fake->assertRequest(function (array $requests): void {
            $this->assertSame('openai', $requests[0]->provider());
            $this->assertSame('text-embedding-3-small', $requests[0]->model());
        });
    }

    public function test_chat_returns_the_text_from_prism_with_no_real_call(): void
    {
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('Dear hiring manager, I am excited to apply...')
                ->withUsage(new Usage(12, 34)),
        ]);

        $text = $this->app->make(AIService::class)->chat('Write a cover letter.', 'You are helpful.');

        $this->assertSame('Dear hiring manager, I am excited to apply...', $text);

        $fake->assertCallCount(1);
        $fake->assertRequest(function (array $requests): void {
            $this->assertSame('openai', $requests[0]->provider());
            $this->assertSame('gpt-4o-mini', $requests[0]->model());
        });
    }

    public function test_match_service_runs_end_to_end_through_faked_prism(): void
    {
        // MatchService embeds the profile (1 embeddings call) then asks for
        // the narrative (1 text call); the job reuses its stored embedding.
        // Responses are consumed in that order by the shared fake sequence.
        $fake = Prism::fake([
            EmbeddingsResponseFake::make()
                ->withEmbeddings([Embedding::fromArray([1.0, 0.0])])
                ->withUsage(new EmbeddingsUsage(5)),
            TextResponseFake::make()
                ->withText('{"summary":"Strong fit","strengths":["PHP","Laravel"],"gaps":["AWS"]}')
                ->withUsage(new Usage(20, 40)),
        ]);

        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel, MySQL',
        ]);
        $job = Job::factory()->active()->create([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $result = $this->app->make(MatchService::class)->score($profile, $job);

        // Aligned [1,0] vectors -> 100%; narrative parsed from the faked JSON.
        $this->assertSame(100, $result->percentage);
        $this->assertSame('Strong fit', $result->summary);
        $this->assertSame(['PHP', 'Laravel'], $result->strengths);
        $this->assertSame(['AWS'], $result->gaps);

        // One embed + one chat, both served by the fake — no real API call.
        $fake->assertCallCount(2);
    }

    public function test_invalid_model_json_over_real_prism_falls_back_to_embedding_only(): void
    {
        // Same wiring, but the model returns prose instead of JSON. The score
        // (embedding-derived) must survive; the narrative degrades cleanly.
        $fake = Prism::fake([
            EmbeddingsResponseFake::make()
                ->withEmbeddings([Embedding::fromArray([1.0, 0.0])])
                ->withUsage(new EmbeddingsUsage(5)),
            TextResponseFake::make()
                ->withText('Sorry, I cannot produce JSON right now.')
                ->withUsage(new Usage(20, 40)),
        ]);

        $profile = CandidateProfile::factory()->for(User::factory()->candidate())->create([
            'headline' => 'Senior PHP Engineer',
            'skills' => 'PHP, Laravel',
        ]);
        $job = Job::factory()->active()->create([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $result = $this->app->make(MatchService::class)->score($profile, $job);

        $this->assertSame(100, $result->percentage);
        $this->assertNotSame('', $result->summary);
        $this->assertSame([], $result->strengths);
        $this->assertSame([], $result->gaps);
        $fake->assertCallCount(2);
    }
}
