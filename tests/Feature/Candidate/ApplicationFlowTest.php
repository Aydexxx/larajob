<?php

namespace Tests\Feature\Candidate;

use App\Jobs\ComputeApplicationMatch;
use App\Models\Application;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Notifications\NewApplicationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    private const COVER_LETTER = 'I am very excited about this opportunity and believe my background is a strong match.';

    private function activeJob(): Job
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();

        return Job::factory()->active()->for($company)->create();
    }

    public function test_a_candidate_can_apply_to_an_active_job_and_the_employer_is_notified(): void
    {
        Notification::fake();

        $job = $this->activeJob();
        $employer = $job->company->user;
        $candidate = User::factory()->candidate()->create();

        $response = $this->actingAs($candidate)->post(route('candidate.applications.store'), [
            'job_id' => $job->id,
            'cover_letter' => self::COVER_LETTER,
        ]);

        $response->assertRedirect(route('candidate.applications.index'));
        $this->assertDatabaseHas('applications', [
            'job_id' => $job->id,
            'user_id' => $candidate->id,
            'status' => 'pending',
        ]);

        Notification::assertSentTo($employer, NewApplicationReceived::class);
    }

    public function test_applying_dispatches_the_match_scoring_job(): void
    {
        Notification::fake();
        Queue::fake();

        $job = $this->activeJob();
        $candidate = User::factory()->candidate()->create();

        $this->actingAs($candidate)->post(route('candidate.applications.store'), [
            'job_id' => $job->id,
            'cover_letter' => self::COVER_LETTER,
        ])->assertRedirect(route('candidate.applications.index'));

        Queue::assertPushed(ComputeApplicationMatch::class);
    }

    public function test_a_candidate_cannot_apply_to_the_same_job_twice(): void
    {
        $job = $this->activeJob();
        $candidate = User::factory()->candidate()->create();
        Application::factory()->pending()->create([
            'job_id' => $job->id,
            'user_id' => $candidate->id,
        ]);

        $response = $this->actingAs($candidate)->post(route('candidate.applications.store'), [
            'job_id' => $job->id,
            'cover_letter' => self::COVER_LETTER,
        ]);

        $response->assertSessionHasErrors('job_id');
        $this->assertSame(1, Application::where('job_id', $job->id)->where('user_id', $candidate->id)->count());
    }

    public function test_a_candidate_cannot_open_the_apply_page_for_a_closed_job(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $closedJob = Job::factory()->for($company)->create(['status' => 'closed', 'expires_at' => null]);

        $candidate = User::factory()->candidate()->create();

        $this->actingAs($candidate)
            ->get(route('candidate.applications.create', ['job_id' => $closedJob->id]))
            ->assertNotFound();
    }

    public function test_a_candidate_can_withdraw_a_pending_application(): void
    {
        $candidate = User::factory()->candidate()->create();
        $application = Application::factory()->pending()->create([
            'job_id' => $this->activeJob()->id,
            'user_id' => $candidate->id,
        ]);

        $this->actingAs($candidate)
            ->delete(route('candidate.applications.destroy', $application))
            ->assertRedirect(route('candidate.applications.index'));

        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    }

    public function test_a_candidate_cannot_withdraw_an_application_already_in_review(): void
    {
        $candidate = User::factory()->candidate()->create();
        $application = Application::factory()->reviewed()->create([
            'job_id' => $this->activeJob()->id,
            'user_id' => $candidate->id,
        ]);

        $this->actingAs($candidate)
            ->delete(route('candidate.applications.destroy', $application))
            ->assertSessionHas('error');

        // The application survives because only pending ones can be withdrawn.
        $this->assertDatabaseHas('applications', ['id' => $application->id]);
    }

    public function test_a_candidate_cannot_view_another_candidates_application(): void
    {
        $owner = User::factory()->candidate()->create();
        $application = Application::factory()->create([
            'job_id' => $this->activeJob()->id,
            'user_id' => $owner->id,
        ]);

        $intruder = User::factory()->candidate()->create();

        $this->actingAs($intruder)
            ->get(route('candidate.applications.show', $application))
            ->assertForbidden();
    }
}
