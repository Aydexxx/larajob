<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\Contracts\AIProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class SemanticJobSearchTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::factory()->for(User::factory()->employer())->create();
    }

    private function enableAi(array $vector = [1.0, 0.0]): void
    {
        $this->app->bind(AIProvider::class, fn () => new FakeAIProvider(enabled: true, vector: $vector));
    }

    public function test_semantic_search_ranks_jobs_by_embedding_similarity(): void
    {
        $company = $this->company();

        $closeMatch = Job::factory()->active()->for($company)->create([
            'title' => 'Senior PHP Engineer',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $farMatch = Job::factory()->active()->for($company)->create([
            'title' => 'Warehouse Associate',
            'embedding' => [0.0, 1.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi(vector: [1.0, 0.0]);

        $content = $this->get(route('jobs.index', ['search' => 'php developer']))
            ->assertOk()
            ->assertSee('Senior PHP Engineer')
            ->assertSee('Warehouse Associate')
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'Warehouse Associate'),
            strpos($content, 'Senior PHP Engineer'),
            'Expected the semantically closer job to be ranked first.'
        );
    }

    public function test_jobs_without_an_embedding_yet_are_excluded_from_semantic_results(): void
    {
        $company = $this->company();

        Job::factory()->active()->for($company)->create([
            'title' => 'Has Embedding',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create(['title' => 'No Embedding Yet']);

        $this->enableAi();

        $this->get(route('jobs.index', ['search' => 'anything']))
            ->assertOk()
            ->assertSee('Has Embedding')
            ->assertDontSee('No Embedding Yet');
    }

    public function test_structured_filters_still_apply_during_semantic_search(): void
    {
        $company = $this->company();

        Job::factory()->active()->for($company)->create([
            'title' => 'Remote Engineer',
            'is_remote' => true,
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);
        Job::factory()->active()->for($company)->create([
            'title' => 'Office Engineer',
            'is_remote' => false,
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $this->get(route('jobs.index', ['search' => 'engineer', 'remote' => 1]))
            ->assertOk()
            ->assertSee('Remote Engineer')
            ->assertDontSee('Office Engineer');
    }

    public function test_smart_search_badge_only_shows_when_semantic_search_is_active(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create([
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $this->get(route('jobs.index', ['search' => 'engineer']))
            ->assertOk()
            ->assertSee('Smart search');

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertDontSee('Smart search');
    }

    public function test_index_falls_back_to_keyword_search_when_ai_is_disabled(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Unicorn Wrangler']);
        Job::factory()->active()->for($company)->create(['title' => 'Database Administrator']);

        // Default test config: AI_PROVIDER=none, no rebind needed.
        $this->get(route('jobs.index', ['search' => 'Unicorn']))
            ->assertOk()
            ->assertSee('Unicorn Wrangler')
            ->assertDontSee('Database Administrator')
            ->assertDontSee('Smart search');
    }

    public function test_index_falls_back_to_keyword_search_when_query_is_empty_even_if_ai_is_enabled(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create([
            'title' => 'Listed Role',
            'embedding' => [1.0, 0.0],
            'embedded_at' => now(),
        ]);

        $this->enableAi();

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Listed Role')
            ->assertDontSee('Smart search');
    }

    public function test_semantic_search_results_are_paginated(): void
    {
        $company = $this->company();

        foreach (range(1, 14) as $i) {
            Job::factory()->active()->for($company)->create([
                'title' => "Engineer Role {$i}",
                'embedding' => [1.0, (float) $i],
                'embedded_at' => now(),
            ]);
        }

        $this->enableAi(vector: [1.0, 0.0]);

        $response = $this->get(route('jobs.index', ['search' => 'engineer']))->assertOk();
        $response->assertViewHas('jobs', fn ($jobs) => $jobs->count() === 12 && $jobs->total() === 14);

        $page2 = $this->get(route('jobs.index', ['search' => 'engineer', 'page' => 2]))->assertOk();
        $page2->assertViewHas('jobs', fn ($jobs) => $jobs->count() === 2);
    }
}
