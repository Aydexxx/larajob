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

    public function test_draft_endpoint_returns_parsed_description_and_requirements(): void
    {
        $this->enableAi('{"description":"We are looking for a great engineer.","requirements":"3+ years PHP\nLaravel experience"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Backend Engineer',
                'bullets' => ['3+ years PHP', 'Laravel experience'],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('draft.description', 'We are looking for a great engineer.')
            ->assertJsonPath('draft.requirements', "3+ years PHP\nLaravel experience");
    }

    public function test_draft_endpoint_returns_a_graceful_error_when_ai_output_is_unparseable(): void
    {
        $this->enableAi('not valid json at all');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Backend Engineer',
                'bullets' => ['3+ years PHP'],
            ])
            ->assertStatus(502)
            ->assertJsonPath('status', 'error');
    }

    public function test_draft_endpoint_requires_a_title_and_at_least_one_bullet(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => '',
                'bullets' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_draft_endpoint_is_not_found_when_ai_disabled(): void
    {
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Backend Engineer',
                'bullets' => ['3+ years PHP'],
            ])
            ->assertNotFound();
    }

    public function test_draft_endpoint_is_rate_limited_per_user(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($employer)
                ->postJson(route('employer.jobs.draft-description'), [
                    'title' => 'Backend Engineer',
                    'bullets' => ['3+ years PHP'],
                ])
                ->assertOk();
        }

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.draft-description'), [
                'title' => 'Backend Engineer',
                'bullets' => ['3+ years PHP'],
            ])
            ->assertStatus(429);
    }

    public function test_job_create_page_shows_generate_button_when_ai_enabled(): void
    {
        $this->enableAi('{"description":"x","requirements":"y"}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertSee('Generate description with AI');
    }

    public function test_job_create_page_hides_generate_button_when_ai_disabled(): void
    {
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertOk()
            ->assertDontSee('Generate description with AI');
    }
}
