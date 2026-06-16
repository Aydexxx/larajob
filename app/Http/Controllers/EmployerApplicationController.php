<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployerApplicationController extends Controller
{
    private function employerCompanyIds(): array
    {
        return Auth::user()->companies()->pluck('id')->toArray();
    }

    public function index(Request $request): View
    {
        $companyIds = $this->employerCompanyIds();
        $jobIds = Job::whereIn('company_id', $companyIds)->pluck('id')->toArray();

        $applications = Application::whereIn('job_id', $jobIds)
            ->with(['job', 'user.candidateProfile'])
            ->when($request->filled('job_id'), fn ($q) => $q->where('job_id', $request->integer('job_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $jobs = Job::whereIn('company_id', $companyIds)->orderBy('title')->get();

        return view('employer.applications.index', compact('applications', 'jobs'));
    }

    public function show(Application $application): View
    {
        $this->authorize('viewAsEmployer', $application);

        $application->load(['job.company', 'user.candidateProfile']);

        return view('employer.applications.show', compact('application'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $this->authorize('updateStatus', $application);

        $request->validate([
            'status' => ['required', 'in:reviewed,accepted,rejected'],
        ]);

        $application->update(['status' => $request->input('status')]);

        $application->loadMissing(['job.company', 'user']);
        $application->user->notify(new ApplicationStatusChanged($application));

        return redirect()->back()->with('success', 'Application status updated.');
    }

    public function downloadResume(Application $application): StreamedResponse
    {
        $this->authorize('viewAsEmployer', $application);

        abort_unless($application->resume_path && Storage::disk('public')->exists($application->resume_path), 404);

        return Storage::disk('public')->download($application->resume_path);
    }
}
