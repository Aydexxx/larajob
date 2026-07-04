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

    /**
     * companies.slug is unique, but company names are free text that
     * different employers can legitimately share or that slugify the same
     * way. Creating a second company with a colliding slug must not 500.
     */
    public function test_two_employers_can_use_company_names_that_slugify_the_same(): void
    {
        $first = User::factory()->employer()->create();
        $second = User::factory()->employer()->create();

        $this->actingAs($first)
            ->post(route('employer.company.store'), ['name' => 'Acme Inc'])
            ->assertRedirect(route('employer.jobs.index'));

        $this->actingAs($second)
            ->post(route('employer.company.store'), ['name' => 'Acme, Inc.'])
            ->assertRedirect(route('employer.jobs.index'));

        $this->assertDatabaseHas('companies', ['name' => 'Acme Inc', 'slug' => 'acme-inc']);
        $this->assertDatabaseHas('companies', ['name' => 'Acme, Inc.', 'slug' => 'acme-inc-2']);
    }

    public function test_saving_a_company_without_changing_its_name_keeps_the_same_slug(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create(['name' => 'Globex Corporation', 'slug' => 'globex-corporation']);

        $this->actingAs($employer)
            ->put(route('employer.company.update'), ['name' => 'Globex Corporation'])
            ->assertRedirect(route('employer.company.edit'));

        $this->assertDatabaseHas('companies', ['id' => $company->id, 'slug' => 'globex-corporation']);
    }
}
