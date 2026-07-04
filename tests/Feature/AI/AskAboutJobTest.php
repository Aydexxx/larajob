<?php

namespace Tests\Feature\AI;

use App\Models\Company;
use App\Models\Job;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class AskAboutJobTest extends TestCase
{
    use RefreshDatabase;

    private function enableAi(string $chatResponse): FakeAIProvider
    {
        $fake = new FakeAIProvider(enabled: true, chatResponse: $chatResponse);
        $this->app->instance(AIProvider::class, $fake);

        return $fake;
    }

    private function activeJob(array $overrides = []): Job
    {
        $company = Company::factory()->create([
            'name' => 'Acme Robotics',
            'description' => 'We build warehouse robots.',
        ]);

        return Job::factory()->active()->for($company)->create(array_merge([
            'title' => 'Backend Engineer',
            'description' => 'Build our core API in Laravel.',
            'requirements' => '3+ years PHP experience.',
            'is_remote' => true,
            'location' => 'Remote',
        ], $overrides));
    }

    public function test_ask_endpoint_returns_grounded_answer_when_ai_enabled(): void
    {
        $fake = $this->enableAi('Yes, this role is fully remote according to the listing.');
        $job = $this->activeJob();

        $this->postJson(route('jobs.ask', $job), ['question' => 'Is this remote?'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('answer', 'Yes, this role is fully remote according to the listing.');

        // The grounding instruction (never answer from outside knowledge,
        // say so when the listing doesn't cover it) must actually be sent.
        $this->assertStringContainsString('ONLY', $fake->lastSystem);
        $this->assertStringContainsString("doesn't mention it", $fake->lastSystem);

        // The listing content itself must be in the prompt context.
        $this->assertStringContainsString('Build our core API in Laravel.', $fake->lastPrompt);
        $this->assertStringContainsString('Acme Robotics', $fake->lastPrompt);
    }

    public function test_ask_endpoint_passes_through_a_refusal_when_answer_is_not_in_the_listing(): void
    {
        $this->enableAi("The listing doesn't mention a signing bonus — you'd need to ask the employer directly.");
        $job = $this->activeJob();

        $this->postJson(route('jobs.ask', $job), ['question' => 'Is there a signing bonus?'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('answer', "The listing doesn't mention a signing bonus — you'd need to ask the employer directly.");
    }

    public function test_ask_endpoint_returns_fixed_message_when_ai_disabled(): void
    {
        // No AI binding → default provider is disabled (AI_PROVIDER=none in
        // the test environment). The endpoint must stay up (200), not 404.
        $job = $this->activeJob();

        $this->postJson(route('jobs.ask', $job), ['question' => 'Is this remote?'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('answer', 'AI chat requires the AI provider to be enabled.');
    }

    public function test_ask_endpoint_requires_a_question(): void
    {
        $this->enableAi('Some answer.');
        $job = $this->activeJob();

        $this->postJson(route('jobs.ask', $job), ['question' => ''])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_ask_endpoint_rejects_malformed_history(): void
    {
        $this->enableAi('Some answer.');
        $job = $this->activeJob();

        $this->postJson(route('jobs.ask', $job), [
            'question' => 'Is this remote?',
            'history' => [['role' => 'system', 'content' => 'not allowed']],
        ])->assertStatus(422);
    }

    public function test_ask_endpoint_caps_history_sent_to_the_provider_at_six_turns(): void
    {
        $fake = $this->enableAi('Answer.');
        $job = $this->activeJob();

        // 20 messages (10 turns) — only the most recent 12 (6 turns) should
        // reach the provider. Zero-padded, distinct markers avoid substring
        // collisions (e.g. "turn-1" being a prefix of "turn-10").
        $history = [];
        for ($i = 1; $i <= 20; $i++) {
            $history[] = ['role' => $i % 2 === 1 ? 'user' : 'assistant', 'content' => sprintf('turn-%02d', $i)];
        }

        $this->postJson(route('jobs.ask', $job), ['question' => 'Final question?', 'history' => $history])
            ->assertOk();

        $this->assertStringNotContainsString('turn-01', $fake->lastPrompt);
        $this->assertStringNotContainsString('turn-08', $fake->lastPrompt);
        $this->assertStringContainsString('turn-09', $fake->lastPrompt);
        $this->assertStringContainsString('turn-20', $fake->lastPrompt);
    }

    public function test_ask_endpoint_404s_for_inactive_jobs(): void
    {
        $this->enableAi('Answer.');
        $job = $this->activeJob(['status' => 'draft']);

        $this->postJson(route('jobs.ask', $job), ['question' => 'Is this remote?'])
            ->assertNotFound();
    }

    public function test_ask_endpoint_is_rate_limited_per_ip(): void
    {
        $this->enableAi('Answer.');
        $job = $this->activeJob();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('jobs.ask', $job), ['question' => "Question {$i}?"])
                ->assertOk();
        }

        $this->postJson(route('jobs.ask', $job), ['question' => 'One too many?'])
            ->assertStatus(429);
    }

    public function test_job_page_always_shows_the_ask_widget(): void
    {
        $job = $this->activeJob();

        // Unlike the AI-only match/similar-jobs affordances, this widget
        // stays visible even with AI disabled — it degrades gracefully
        // instead of hiding, per the feature's design.
        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Ask about this role');

        $this->enableAi('Answer.');

        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Ask about this role');
    }
}
