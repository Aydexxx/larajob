<?php

namespace Tests\Feature\AI;

use App\Models\Company;
use App\Models\User;
use App\Services\AI\BiasCheckResult;
use App\Services\AI\BiasCheckService;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class BiasCheckTest extends TestCase
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

    public function test_endpoint_returns_model_flags_when_ai_enabled(): void
    {
        $this->enableAi('{"flags":[{"phrase":"rockstar developer","issue":"Hype term skews masculine.","suggestion":"skilled developer"}]}');
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.check-bias'), ['text' => 'We want a rockstar developer.'])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('result.source', 'model')
            ->assertJsonPath('result.flags.0.phrase', 'rockstar developer')
            ->assertJsonPath('result.flags.0.suggestion', 'skilled developer');
    }

    public function test_endpoint_uses_keyword_fallback_when_ai_disabled(): void
    {
        // No AI binding → disabled. The endpoint must still work (not 404),
        // flagging known coded terms via the deterministic scan.
        $employer = $this->employerWithCompany();

        $response = $this->actingAs($employer)
            ->postJson(route('employer.jobs.check-bias'), [
                'text' => 'We need a young, energetic rockstar to join the guys.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('result.source', 'rules');

        $phrases = collect($response->json('result.flags'))->pluck('phrase');
        $this->assertContains('rockstar', $phrases);
        $this->assertContains('young', $phrases);
        $this->assertContains('guys', $phrases);

        // Every flag carries a suggested rewrite.
        foreach ($response->json('result.flags') as $flag) {
            $this->assertNotSame('', $flag['suggestion']);
        }
    }

    public function test_keyword_scan_returns_no_flags_for_clean_text(): void
    {
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.check-bias'), [
                'text' => 'We are hiring a backend engineer to build our API.',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('result.flags', []);
    }

    public function test_service_falls_back_to_keywords_when_model_output_is_unparseable(): void
    {
        $this->enableAi('not valid json');

        $result = $this->app->make(BiasCheckService::class)->check('We want a rockstar.');

        $this->assertSame(BiasCheckResult::SOURCE_RULES, $result->source);
        $this->assertSame('rockstar', $result->flags[0]['phrase']);
    }

    public function test_endpoint_requires_text(): void
    {
        $employer = $this->employerWithCompany();

        $this->actingAs($employer)
            ->postJson(route('employer.jobs.check-bias'), ['text' => ''])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_endpoint_requires_employer_authentication(): void
    {
        // Guests are bounced by the employer route group's auth middleware.
        $this->post(route('employer.jobs.check-bias'), ['text' => 'We want a rockstar.'])
            ->assertRedirect(route('login'));
    }
}
