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
            'description' => 'Build and maintain our core platform services.',
            'requirements' => 'Five years of PHP experience.',
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
        ]);
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
