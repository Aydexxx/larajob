<?php

namespace Tests\Feature\Employer;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an employer that already owns a company.
     *
     * @return array{0: User, 1: Company}
     */
    private function employerWithCompany(): array
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return [$employer, $company];
    }

    private function validJobPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Senior Software Engineer',
            'description' => 'We are looking for a Senior Software Engineer to help design, build, and maintain the core services behind our platform. You will work closely with product and design to ship features end to end, and take ownership of reliability and performance for the systems you own.',
            'requirements' => 'Five or more years of professional PHP experience, including Laravel. Comfortable working with relational databases and writing automated tests.',
            'type' => 'full-time',
            'location' => 'Remote',
            'is_remote' => true,
            'salary_min' => 90000,
            'salary_max' => 130000,
        ], $overrides);
    }

    public function test_an_employer_can_post_a_job(): void
    {
        [$employer, $company] = $this->employerWithCompany();

        $response = $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload());

        $response->assertRedirect(route('employer.jobs.index'));
        $this->assertDatabaseHas('job_listings', [
            'company_id' => $company->id,
            'title' => 'Senior Software Engineer',
            'status' => 'active',
            'slug' => 'senior-software-engineer',
        ]);
    }

    /**
     * job_listings.slug is a unique column, but job titles are free text
     * that different employers legitimately reuse. Posting a second job
     * with a title that slugifies the same way must not 500 — it must get
     * a de-duplicated slug instead.
     */
    public function test_two_employers_can_post_jobs_with_the_same_title(): void
    {
        [$firstEmployer] = $this->employerWithCompany();
        [$secondEmployer] = $this->employerWithCompany();

        $this->actingAs($firstEmployer)
            ->post(route('employer.jobs.store'), $this->validJobPayload(['title' => 'Product Manager']))
            ->assertRedirect(route('employer.jobs.index'));

        $this->actingAs($secondEmployer)
            ->post(route('employer.jobs.store'), $this->validJobPayload(['title' => 'Product Manager']))
            ->assertRedirect(route('employer.jobs.index'));

        $this->assertDatabaseHas('job_listings', ['title' => 'Product Manager', 'slug' => 'product-manager']);
        $this->assertDatabaseHas('job_listings', ['title' => 'Product Manager', 'slug' => 'product-manager-2']);
    }

    public function test_saving_a_job_without_changing_its_title_keeps_the_same_slug(): void
    {
        [$employer, $company] = $this->employerWithCompany();
        $job = Job::factory()->for($company)->create(['title' => 'Data Analyst', 'slug' => 'data-analyst']);

        $this->actingAs($employer)
            ->put(route('employer.jobs.update', $job), $this->validJobPayload(['title' => 'Data Analyst']))
            ->assertRedirect(route('employer.jobs.index'));

        $this->assertDatabaseHas('job_listings', ['id' => $job->id, 'slug' => 'data-analyst']);
    }

    public function test_a_job_description_shorter_than_the_minimum_is_rejected(): void
    {
        [$employer] = $this->employerWithCompany();

        $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload(['description' => 'Too short.']))
            ->assertSessionHasErrors('description');

        $this->assertDatabaseMissing('job_listings', ['title' => 'Senior Software Engineer']);
    }

    public function test_job_requirements_are_optional_but_rejected_when_too_short(): void
    {
        [$employer] = $this->employerWithCompany();

        $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload(['requirements' => 'PHP.']))
            ->assertSessionHasErrors('requirements');

        $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload(['requirements' => '']))
            ->assertSessionDoesntHaveErrors('requirements');
    }

    public function test_location_is_required_for_a_non_remote_job(): void
    {
        [$employer] = $this->employerWithCompany();

        $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload([
                'is_remote' => false,
                'location' => '',
            ]))
            ->assertSessionHasErrors('location');
    }

    public function test_location_is_not_required_for_a_remote_job(): void
    {
        [$employer] = $this->employerWithCompany();

        // A real checked HTML checkbox submits the string "1" (never a
        // native PHP bool), which is what required_unless:is_remote,1
        // actually matches against.
        $this->actingAs($employer)
            ->post(route('employer.jobs.store'), $this->validJobPayload([
                'is_remote' => '1',
                'location' => '',
            ]))
            ->assertSessionDoesntHaveErrors('location');
    }

    public function test_an_employer_can_edit_their_own_job(): void
    {
        [$employer, $company] = $this->employerWithCompany();
        $job = Job::factory()->for($company)->create(['title' => 'Old Title']);

        $response = $this->actingAs($employer)->put(
            route('employer.jobs.update', $job),
            $this->validJobPayload(['title' => 'Updated Title'])
        );

        $response->assertRedirect(route('employer.jobs.index'));
        $this->assertDatabaseHas('job_listings', [
            'id' => $job->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_an_employer_can_delete_their_own_job(): void
    {
        [$employer, $company] = $this->employerWithCompany();
        $job = Job::factory()->for($company)->create();

        $this->actingAs($employer)
            ->delete(route('employer.jobs.destroy', $job))
            ->assertRedirect(route('employer.jobs.index'));

        $this->assertDatabaseMissing('job_listings', ['id' => $job->id]);
    }

    // --- Authorization boundaries: an employer must not touch another's jobs ---

    public function test_an_employer_cannot_edit_another_employers_job(): void
    {
        [$employer] = $this->employerWithCompany();

        $otherCompany = Company::factory()->for(User::factory()->employer())->create();
        $foreignJob = Job::factory()->for($otherCompany)->create();

        $this->actingAs($employer)
            ->get(route('employer.jobs.edit', $foreignJob))
            ->assertForbidden();
    }

    public function test_an_employer_cannot_update_another_employers_job(): void
    {
        [$employer] = $this->employerWithCompany();

        $otherCompany = Company::factory()->for(User::factory()->employer())->create();
        $foreignJob = Job::factory()->for($otherCompany)->create(['title' => 'Hands Off']);

        // Payload is valid, so a 403 proves authorization (not validation) stopped it.
        $this->actingAs($employer)
            ->put(route('employer.jobs.update', $foreignJob), $this->validJobPayload(['title' => 'Hijacked']))
            ->assertForbidden();

        $this->assertDatabaseHas('job_listings', [
            'id' => $foreignJob->id,
            'title' => 'Hands Off',
        ]);
    }

    public function test_an_employer_cannot_delete_another_employers_job(): void
    {
        [$employer] = $this->employerWithCompany();

        $otherCompany = Company::factory()->for(User::factory()->employer())->create();
        $foreignJob = Job::factory()->for($otherCompany)->create();

        $this->actingAs($employer)
            ->delete(route('employer.jobs.destroy', $foreignJob))
            ->assertForbidden();

        $this->assertDatabaseHas('job_listings', ['id' => $foreignJob->id]);
    }
}
