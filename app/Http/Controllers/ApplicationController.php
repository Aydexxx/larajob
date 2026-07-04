<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApplicationRequest;
use App\Jobs\ComputeApplicationMatch;
use App\Models\Application;
use App\Models\Job;
use App\Notifications\NewApplicationReceived;
use App\Services\AI\CoverLetterDraftService;
use App\Services\Resume\ResumeStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ApplicationController extends Controller
{
    public function create(CoverLetterDraftService $drafts): View|RedirectResponse
    {
        $job = Job::active()->findOrFail(request()->integer('job_id'));

        $alreadyApplied = Application::where('job_id', $job->id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($alreadyApplied) {
            return redirect()->route('candidate.applications.index')
                ->with('info', 'You have already applied to this job.');
        }

        $job->load('company');

        // "Draft with AI" is hidden entirely when the AI layer is off.
        $aiAssistEnabled = $drafts->isAvailable();

        return view('candidate.applications.create', compact('job', 'aiAssistEnabled'));
    }

    public function store(StoreApplicationRequest $request, ResumeStorage $resumes): RedirectResponse
    {
        // Use the freshly uploaded file if there is one; otherwise fall back
        // to a snapshot of the candidate's profile resume — matching what the
        // apply form promises ("your profile resume will be used"). copy()
        // gives the application its own immutable copy, so later replacing the
        // profile resume never breaks this submission.
        $resumePath = $request->hasFile('resume')
            ? $resumes->store($request->file('resume'))
            : $resumes->copy(Auth::user()->candidateProfile?->resume_path);

        $application = Application::create([
            'job_id' => $request->validated('job_id'),
            'user_id' => Auth::id(),
            'cover_letter' => $request->validated('cover_letter'),
            'resume_path' => $resumePath,
            'status' => 'pending',
        ]);

        $job = Job::with('company.user')->find($application->job_id);
        if ($job?->company?->user) {
            $job->company->user->notify(new NewApplicationReceived($application, $job));
        }

        // Warm the match-score cache off the request cycle so the employer's
        // applications list can show and sort by it without a live AI call.
        ComputeApplicationMatch::dispatch($application->id);

        return redirect()->route('candidate.applications.index')
            ->with('success', 'Application submitted successfully.');
    }

    public function index(): View
    {
        $applications = Auth::user()
            ->applications()
            ->with('job.company')
            ->latest()
            ->get();

        return view('candidate.applications.index', compact('applications'));
    }

    public function show(Application $application): View
    {
        $this->authorize('view', $application);

        $application->load('job.company');

        return view('candidate.applications.show', compact('application'));
    }

    /**
     * View the resume submitted with the candidate's own application. Served
     * via a signed, expiring URL (S3/R2) or an app-streamed response (local)
     * so the file is never publicly reachable.
     */
    public function resume(Application $application, ResumeStorage $resumes): Response
    {
        $this->authorize('view', $application);

        abort_unless(filled($application->resume_path), 404);

        return $resumes->view($application->resume_path, 'resume.pdf');
    }

    public function destroy(Application $application): RedirectResponse
    {
        $this->authorize('delete', $application);

        if ($application->status !== 'pending') {
            return back()->with('error', 'Only pending applications can be withdrawn.');
        }

        $application->delete();

        return redirect()->route('candidate.applications.index')
            ->with('success', 'Application withdrawn.');
    }
}
