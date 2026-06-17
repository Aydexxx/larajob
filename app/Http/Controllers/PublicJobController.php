<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Services\AI\JobSearchService;
use App\Services\AI\MatchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PublicJobController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request, JobSearchService $jobSearch): View
    {
        $term = $request->input('search');

        $filtered = $this->applyStructuredFilters(Job::active(), $request);

        $semanticResults = filled($term) ? $jobSearch->rankByQuery($term, $filtered) : null;

        if ($semanticResults !== null) {
            $jobs = $this->paginate($semanticResults, $request);
            $isSemanticSearch = true;
        } else {
            $jobs = $filtered->with('company')
                ->search($term)
                ->latest()
                ->paginate(self::PER_PAGE)
                ->withQueryString();
            $isSemanticSearch = false;
        }

        return view('jobs.index', compact('jobs', 'isSemanticSearch'));
    }

    public function show(Job $job, JobSearchService $jobSearch, MatchService $matches): View
    {
        abort_unless($job->status === 'active', 404);

        $job->load('company');

        $user = Auth::user();
        $isCandidate = $user?->role === 'candidate';

        $hasApplied = $isCandidate
            && Application::where('job_id', $job->id)->where('user_id', $user->id)->exists();

        $similarJobs = $jobSearch->similarTo($job);

        // Match card: candidates only, and entirely hidden when AI is off.
        $profile = $isCandidate ? $user->candidateProfile : null;
        $matchEnabled = $isCandidate && $matches->isAvailable();
        $matchIncomplete = $matchEnabled && ! $matches->profileIsScorable($profile);
        $matchInitial = ($matchEnabled && ! $matchIncomplete)
            ? $matches->cached($profile, $job)
            : null;

        return view('jobs.show', compact(
            'job', 'hasApplied', 'similarJobs', 'matchEnabled', 'matchIncomplete', 'matchInitial'
        ));
    }

    /**
     * Apply the structured (non-keyword) filters shared by both the
     * keyword and semantic search paths.
     */
    private function applyStructuredFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when(
                $request->filled('location'),
                fn ($q) => $q->where('location', 'like', '%'.$request->input('location').'%')
            )
            ->when(
                $request->filled('company'),
                fn ($q) => $q->whereHas('company', fn ($c) => $c->where('name', 'like', '%'.$request->input('company').'%'))
            )
            ->when(
                $request->filled('types'),
                fn ($q) => $q->filterType((array) $request->input('types'))
            )
            ->when($request->boolean('remote'), fn ($q) => $q->remote())
            ->when(
                $request->filled('salary_min'),
                fn ($q) => $q->where('salary_max', '>=', (int) $request->input('salary_min'))
            );
    }

    /**
     * Paginate an already-ranked, in-memory collection the same way
     * Builder::paginate() would, so the view (links, total, etc.) doesn't
     * need to know whether results came from SQL or semantic ranking.
     *
     * @param  Collection<int, Job>  $items
     */
    private function paginate(Collection $items, Request $request): LengthAwarePaginator
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
