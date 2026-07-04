<?php

namespace Tests\Feature\AI;

use App\Models\Company;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class JobDescriptionDraftTest extends TestCase
{
    use RefreshDatabase;

    private function enableAi(string $chatResponse): void
    {
        $this->app->instance(AIProvider::class, new FakeAIProvider(
            enabled: true,
            chatResponse: $chatResponse,
        ));
    }

    private function employerWithCompany(): User
    {
        $employer = User::factory()->employer()->create();
        Company::factory()->for($employer)->create();

        return $employer;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Backend Engineer',
            'seniority' => 'Senior',
            'skills' => ['PHP', 'Laravel'],
            'location' => 'Berlin',
            'salary' => '€60k–€80k',
        ], $overrides);
    }

    public function test_draft_endpoint_returns_parsed_model_draft_when_ai_enabled(): void
    {
        $this->enableAi('{"description":"We are looking for a great engineer.","requirements":"3+ years PHP\nLaravel experience"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft.description', 'We are looking for a great engineer.')
            ->assertJsonPath('draft.requirements', "3+ years PHP\nLaravel experience")
            ->assertJsonPath('draft.source', 'model');
    }

    public function test_draft_endpoint_assembles_a_template_when_ai_disabled(): void
    {
        // No AI binding → default provider is disabled (AI_PROVIDER=none in
        // the test environment). The endpoint must NOT 404: it assembles a
        // deterministic template from the structured inputs, no API call.
        $employer = $this->employerWithCompany();

        $response = $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft.source', 'template');

        // The template must actually reflect the inputs it was given.
        $draft = $response->json('draft');
        $this->assertStringContainsString('Senior Backend Engineer', $draft['description']);
        $this->assertStringContainsString('Berlin', $draft['description']);
        $this->assertStringContainsString('€60k–€80k', $draft['description']);
        $this->assertStringContainsString('PHP', $draft['requirements']);
        $this->assertStringContainsString('Laravel', $draft['requirements']);
    }

    public function test_draft_endpoint_falls_back_to_template_when_model_output_is_unparseable(): void
    {
        // Even with AI enabled, garbage output degrades to the template
        // rather than erroring — the generator is always useful.
        $this->enableAi('not valid json at all');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft.source', 'template');
    }

    public function test_draft_endpoint_requires_a_title(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), $this->payload(['title' => '']))
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_draft_endpoint_works_with_only_a_title(): void
    {
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Customer Success Lead',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft.source', 'template');
    }

    public function test_draft_endpoint_is_rate_limited_per_user(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($employer)
                ->postJson(route('employer.jobs.draft-description'), $this->payload())
                ->assertOk();
        }

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), $this->payload())
            ->assertStatus(429);
    }

    public function test_job_create_page_shows_ai_generate_button_when_ai_enabled(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertSee('Generate description with AI');
    }

    public function test_job_create_page_shows_template_generator_when_ai_disabled(): void
    {
        $employer = $this->employerWithCompany();

        // The generator is still offered offline, but must not claim to be
        // AI (the degradation test guards the AI phrasing specifically).
        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertSee('Draft a starter description')
            ->assertDontSee('Generate description with AI');
    }
}
