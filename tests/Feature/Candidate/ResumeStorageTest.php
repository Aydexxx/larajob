<?php

namespace Tests\Feature\Candidate;

use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Resumes must live on the private, env-selected resume disk (S3/R2 in
 * production, private local disk in dev) — never the public disk — and be
 * reachable only through authorized, signed/streamed URLs. This covers the
 * storage location, safe filenames, replace-on-reupload, server-side
 * validation, and access control on every view path.
 */
class ResumeStorageTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // Parsing/embedding jobs are irrelevant here and would hit the queue.
        Queue::fake();

        $this->disk = config('filesystems.resume_disk');
        Storage::fake($this->disk);
    }

    private function candidate(): User
    {
        $user = User::factory()->candidate()->create();
        CandidateProfile::factory()->for($user)->create();

        return $user->fresh();
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'resume.pdf',
            file_get_contents(base_path('tests/Support/fixtures/resume.pdf'))
        );
    }

    public function test_resume_disk_defaults_to_a_private_local_disk_without_s3_env(): void
    {
        // In the test environment no S3 bucket is configured, so uploads stay
        // on the private local disk rather than the public one.
        $this->assertSame('local', config('filesystems.resume_disk'));
        $this->assertNotSame('public', config('filesystems.resume_disk'));
    }

    public function test_uploaded_resume_is_stored_on_the_resume_disk_with_a_safe_pdf_filename(): void
    {
        $user = $this->candidate();

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), ['resume' => $this->pdf()])
            ->assertRedirect(route('candidate.profile.edit'));

        $path = $user->candidateProfile->fresh()->resume_path;

        // Random, always-".pdf" name — the client filename never leaks in.
        $this->assertMatchesRegularExpression('#^resumes/[A-Za-z0-9]{40}\.pdf$#', $path);
        Storage::disk($this->disk)->assertExists($path);
    }

    public function test_reuploading_a_resume_replaces_and_deletes_the_previous_file(): void
    {
        $user = $this->candidate();

        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);
        $first = $user->candidateProfile->fresh()->resume_path;

        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);
        $second = $user->candidateProfile->fresh()->resume_path;

        $this->assertNotSame($first, $second);
        Storage::disk($this->disk)->assertMissing($first);
        Storage::disk($this->disk)->assertExists($second);
    }

    public function test_a_non_pdf_upload_is_rejected(): void
    {
        $user = $this->candidate();

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), [
                'resume' => UploadedFile::fake()->createWithContent('resume.png', 'not a pdf'),
            ])
            ->assertSessionHasErrors('resume');

        $this->assertNull($user->candidateProfile->fresh()->resume_path);
    }

    public function test_a_resume_larger_than_5mb_is_rejected(): void
    {
        $user = $this->candidate();

        // Real PDF header (so it sniffs as application/pdf) padded past 5 MB,
        // proving it's the size rule — not the type rule — that rejects it.
        $oversized = UploadedFile::fake()->createWithContent(
            'resume.pdf',
            file_get_contents(base_path('tests/Support/fixtures/resume.pdf')).str_repeat('0', 6 * 1024 * 1024)
        );

        $this->actingAs($user)
            ->put(route('candidate.profile.update'), ['resume' => $oversized])
            ->assertSessionHasErrors('resume');

        $this->assertNull($user->candidateProfile->fresh()->resume_path);
    }

    public function test_candidate_can_view_their_own_profile_resume_via_a_temporary_url(): void
    {
        $user = $this->candidate();
        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);

        // The private disk hands out a short-lived, expiring URL rather than a
        // permanent public path (the fake disk signs it with ?expiration=).
        $response = $this->actingAs($user)->get(route('candidate.profile.resume'));

        $response->assertRedirect();
        $this->assertStringContainsString('expiration=', $response->headers->get('Location'));
    }

    public function test_viewing_a_profile_resume_that_does_not_exist_is_404(): void
    {
        $this->actingAs($this->candidate())
            ->get(route('candidate.profile.resume'))
            ->assertNotFound();
    }

    public function test_profile_resume_view_redirects_to_a_signed_url_when_the_disk_supports_it(): void
    {
        // Emulate an S3/R2-backed disk by teaching the (faked) resume disk to
        // mint temporary URLs; the view must redirect to one, never stream.
        Storage::disk($this->disk)->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options) => 'https://signed.example/'.$path
        );

        $user = $this->candidate();
        $this->actingAs($user)->put(route('candidate.profile.update'), ['resume' => $this->pdf()]);
        $path = $user->candidateProfile->fresh()->resume_path;

        $this->actingAs($user)
            ->get(route('candidate.profile.resume'))
            ->assertRedirect('https://signed.example/'.$path);
    }

    public function test_candidate_cannot_view_another_candidates_application_resume(): void
    {
        $owner = $this->candidate();
        $application = Application::factory()->for($owner)->create([
            'resume_path' => app(\App\Services\Resume\ResumeStorage::class)->store($this->pdf()),
        ]);

        $this->actingAs($this->candidate())
            ->get(route('candidate.applications.resume', $application))
            ->assertForbidden();
    }

    public function test_owning_employer_can_view_applicant_resume_but_a_stranger_employer_cannot(): void
    {
        $employer = User::factory()->employer()->create();
        $company = Company::factory()->for($employer)->create();
        $job = Job::factory()->active()->for($company)->create();

        $application = Application::factory()->for($job)->for($this->candidate())->create([
            'resume_path' => app(\App\Services\Resume\ResumeStorage::class)->store($this->pdf()),
        ]);

        $this->actingAs($employer)
            ->get(route('employer.applications.resume', $application))
            ->assertRedirect();

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('employer.applications.resume', $application))
            ->assertForbidden();
    }
}
