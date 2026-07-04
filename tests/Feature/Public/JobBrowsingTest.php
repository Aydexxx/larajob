<?php

namespace Tests\Feature\Public;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private function company(): Company
    {
        return Company::factory()->for(User::factory()->employer())->create();
    }

    public function test_the_homepage_loads(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Find a job');
    }

    public function test_the_job_index_lists_active_jobs(): void
    {
        $job = Job::factory()->active()->for($this->company())->create(['title' => 'Open Role']);

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Open Role');
    }

    public function test_search_filters_jobs_by_title(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Unicorn Wrangler']);
        Job::factory()->active()->for($company)->create(['title' => 'Database Administrator']);

        $this->get(route('jobs.index', ['search' => 'Unicorn']))
            ->assertOk()
            ->assertSee('Unicorn Wrangler')
            ->assertDontSee('Database Administrator');
    }

    public function test_type_filter_only_returns_matching_employment_types(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Contract Gig', 'type' => 'contract']);
        Job::factory()->active()->for($company)->create(['title' => 'Permanent Post', 'type' => 'full-time']);

        $this->get(route('jobs.index', ['types' => ['contract']]))
            ->assertOk()
            ->assertSee('Contract Gig')
            ->assertDontSee('Permanent Post');
    }

    public function test_remote_filter_only_returns_remote_jobs(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Anywhere Role', 'is_remote' => true]);
        Job::factory()->active()->for($company)->create(['title' => 'Office Role', 'is_remote' => false]);

        $this->get(route('jobs.index', ['remote' => 1]))
            ->assertOk()
            ->assertSee('Anywhere Role')
            ->assertDontSee('Office Role');
    }

    public function test_closed_jobs_are_hidden_from_the_index(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Live Role']);
        Job::factory()->for($company)->create(['title' => 'Closed Role', 'status' => 'closed', 'expires_at' => null]);

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Live Role')
            ->assertDontSee('Closed Role');
    }

    public function test_expired_jobs_are_hidden_from_the_index(): void
    {
        $company = $this->company();
        Job::factory()->active()->for($company)->create(['title' => 'Current Role']);
        Job::factory()->for($company)->create([
            'title' => 'Expired Role',
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('Current Role')
            ->assertDontSee('Expired Role');
    }

    public function test_an_active_job_detail_page_is_viewable(): void
    {
        $job = Job::factory()->active()->for($this->company())->create(['title' => 'Detailed Role']);

        $this->get(route('jobs.show', $job))
            ->assertOk()
            ->assertSee('Detailed Role');
    }

    public function test_a_closed_job_detail_page_returns_404(): void
    {
        $job = Job::factory()->for($this->company())->create(['status' => 'closed', 'expires_at' => null]);

        $this->get(route('jobs.show', $job))->assertNotFound();
    }

    // --- Empty states: a genuinely empty board vs no results for a filter ---

    public function test_a_genuinely_empty_board_shows_a_no_jobs_posted_yet_message(): void
    {
        $this->get(route('jobs.index'))
            ->assertOk()
            ->assertSee('No jobs posted yet')
            ->assertDontSee('No jobs match your search');
    }

    public function test_no_results_for_a_search_on_a_non_empty_board_shows_a_filter_specific_message(): void
    {
        Job::factory()->active()->for($this->company())->create(['title' => 'Existing Role']);

        $this->get(route('jobs.index', ['search' => 'Nonexistent Title Xyz']))
            ->assertOk()
            ->assertSee('No jobs match your search')
            ->assertDontSee('No jobs posted yet');
    }
}
