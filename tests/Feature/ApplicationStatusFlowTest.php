<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-end status pipeline: an employer updates an application's status,
 * the candidate is notified, and the new status is visible to the candidate.
 */
class ApplicationStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{employer: User, candidate: User, application: Application}
     */
    private function scenario(): array
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $job = Job::factory()->active()->for($company)->create();
        $candidate = User::factory()->candidate()->create();

        $application = Application::factory()->pending()->create([
            'job_id' => $job->id,
            'user_id' => $candidate->id,
        ]);

        return compact('employer', 'candidate', 'application');
    }

    public function test_employer_updates_status_and_the_candidate_sees_it(): void
    {
        Notification::fake();

        ['employer' => $employer, 'candidate' => $candidate, 'application' => $application] = $this->scenario();

        $response = $this->actingAs($employer)
            ->from(route('employer.applications.show', $application))
            ->patch(route('employer.applications.update-status', $application), [
                'status' => 'accepted',
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'accepted',
        ]);

        Notification::assertSentTo($candidate, ApplicationStatusChanged::class);

        // The candidate can now see the updated status on their application page.
        $this->actingAs($candidate)
            ->get(route('candidate.applications.show', $application))
            ->assertOk()
            ->assertSee('Accepted');
    }

    public function test_invalid_status_values_are_rejected(): void
    {
        ['employer' => $employer, 'application' => $application] = $this->scenario();

        $this->actingAs($employer)
            ->from(route('employer.applications.show', $application))
            ->patch(route('employer.applications.update-status', $application), [
                'status' => 'banana',
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);
    }

    public function test_an_employer_cannot_update_status_for_another_companys_application(): void
    {
        ['application' => $application] = $this->scenario();

        $otherEmployer = User::factory()->employer()->create();
        Company::factory()->for($otherEmployer)->create();

        $this->actingAs($otherEmployer)
            ->patch(route('employer.applications.update-status', $application), [
                'status' => 'accepted',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'pending',
        ]);
    }
}
