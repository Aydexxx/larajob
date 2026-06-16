<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employer_can_create_a_company_profile(): void
    {
        $employer = User::factory()->employer()->create();

        $response = $this->actingAs($employer)->post(route('employer.company.store'), [
            'name' => 'Globex Corporation',
            'description' => 'We build the future.',
            'website' => 'https://globex.example.com',
            'location' => 'Berlin, Germany',
        ]);

        $response->assertRedirect(route('employer.jobs.index'));
        $this->assertDatabaseHas('companies', [
            'user_id' => $employer->id,
            'name' => 'Globex Corporation',
            'slug' => 'globex-corporation',
        ]);
    }

    public function test_an_employer_without_a_company_is_redirected_to_create_one_before_posting_jobs(): void
    {
        $employer = User::factory()->employer()->create();

        $this->actingAs($employer)
            ->get(route('employer.jobs.create'))
            ->assertRedirect(route('employer.company.create'));
    }

    public function test_an_employer_can_only_own_a_single_company(): void
    {
        $employer = User::factory()->employer()->create();
        Company::factory()->for($employer)->create();

        // Visiting "create" again bounces to edit instead of allowing a second company.
        $this->actingAs($employer)
            ->get(route('employer.company.create'))
            ->assertRedirect(route('employer.company.edit'));
    }
}
