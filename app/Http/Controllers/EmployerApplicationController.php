<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Notifications\ApplicationStatusChanged;
use App\Services\AI\MatchService;
use App\Services\Resume\ResumeStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EmployerApplicationController extends Controller
{
    private const PER_PAGE = 20;

    private function employerCompanyIds(): array
    {
        return Auth::user()->companies()->pluck('id')->toArray();
    }

    public function index(Request $request, MatchService $matches): View
    {
        $companyIds = $this->employerCompanyIds();
        $jobIds = Job::whereIn('company_id', $companyIds)->pluck('id')->toArray();

        $query = Application::whereIn('job_id', $jobIds)
            ->with(['job', 'user.candidateProfile'])
            ->when($request->filled('job_id'), fn ($q) => $q->where('job_id', $request->integer('job_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')));

        $aiEnabled = $matches->isAvailable();
        $sortByMatch = $aiEnabled && $request->input('sort') === 'match';

        if ($sortByMatch) {
            // Match scores live in the cache, not the DB, so a true sort
            // needs them in memory. Load the filtered set, attach cached
            // scores (cache-only — never an LLM call here), sort, and
            // paginate by hand. Uncomputed scores sort last.
            $all = $query->latest()->get();
            $this->attachMatchScores($all, $matches);

            $sorted = $all->sortByDesc(fn (Application $a) => $a->match_percentage ?? -1)->values();
            $applications = $this->paginateCollection($sorted, $request);
        } else {
            $applications = $query->latest()->paginate(self::PER_PAGE)->withQueryString();
            $this->attachMatchScores($applications->getCollection(), $matches);
        }

        $jobs = Job::whereIn('company_id', $companyIds)->orderBy('title')->get();

        return view('employer.applications.index', compact('applications', 'jobs', 'aiEnabled', 'sortByMatch'));
    }

    public function show(Application $application, MatchService $matches): View
    {
        $this->authorize('viewAsEmployer', $application);

        $application->load(['job.company', 'user.candidateProfile']);

        $profile = $application->user?->candidateProfile;

        $matchEnabled = $matches->isAvailable() && $application->job !== null;
        $matchIncomplete = $matchEnabled && ! $matches->profileIsScorable($profile);
        $matchInitial = ($matchEnabled && ! $matchIncomplete)
            ? $matches->cached($profile, $application->job)
            : null;

        return view('employer.applications.show', compact(
            'application', 'matchEnabled', 'matchIncomplete', 'matchInitial'
        ));
    }

    /**
     * On-demand match breakdown for a single application, fetched
     * asynchronously by the detail page so the page never blocks on AI.
     */
    public function match(Application $application, MatchService $matches): JsonResponse
    {
        $this->authorize('viewAsEmployer', $application);
        abort_unless($matches->isAvailable(), 404);

        $application->loadMissing(['job', 'user.candidateProfile']);
        $profile = $application->user?->candidateProfile;

        if (! $application->job || ! $matches->profileIsScorable($profile)) {
            return response()->json(['status' => 'incomplete_profile', 'match' => null]);
        }

        return response()->json([
            'status' => 'ok',
            'match' => $matches->score($profile, $application->job)->toArray(),
        ]);
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

    public function downloadResume(Application $application, ResumeStorage $resumes): Response
    {
        $this->authorize('viewAsEmployer', $application);

        abort_unless(filled($application->resume_path), 404);

        return $resumes->view($application->resume_path, 'resume.pdf');
    }

    /**
     * Attach the cached match result (and a convenience percentage) to each
     * application for display/sorting. Cache-only: never computes, never
     * calls the AI provider, no-op entirely when AI is disabled.
     *
     * @param  Collection<int, Application>  $applications
     */
    private function attachMatchScores(Collection $applications, MatchService $matches): void
    {
        if (! $matches->isAvailable()) {
            return;
        }

        $applications->each(function (Application $application) use ($matches): void {
            $profile = $application->user?->candidateProfile;

            $result = ($application->job && $matches->profileIsScorable($profile))
                ? $matches->cached($profile, $application->job)
                : null;

            $application->match_result = $result;
            $application->match_percentage = $result?->percentage;
        });
    }

    /**
     * Manually paginate an in-memory, already-sorted collection so the view
     * keeps working with $applications->links()/->total() exactly as for the
     * DB-paginated path.
     *
     * @param  Collection<int, Application>  $items
     */
    private function paginateCollection(Collection $items, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, self::PER_PAGE)->values(),
            $items->count(),
            self::PER_PAGE,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'query' => $request->query(),
            ]
        );
    }
}
