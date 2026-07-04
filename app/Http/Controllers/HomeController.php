<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Repositories\JobMatchRepository;
use App\Services\AI\MatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** How many roles the personalized feed shows before "browse all". */
    private const FEED_LIMIT = 12;

    public function index(MatchService $matches, JobMatchRepository $repository): View
    {
        $user = Auth::user();

        // Signed-in candidates land on their personalized feed — but only
        // when the AI layer is actually on. With AI off there is nothing to
        // rank by, so everyone sees the marketing home (and no AI surface
        // leaks, keeping the disabled-mode contract intact).
        if ($user?->role === 'candidate' && $matches->isAvailable()) {
            return $this->forYou($user, $matches, $repository);
        }

        return $this->marketingHome();
    }

    /**
     * The personalized "For You" feed. Resolves to one of four states:
     *
     *  - unscorable: profile lacks the substance to match on → prompt to
     *    complete it, with newest roles as a fallback list.
     *  - pending: profile is ready but its embedding hasn't been generated
     *    yet (async pipeline / awaiting backfill) → "analyzing" state.
     *  - ranked: embedding present → roles ordered by match score, each
     *    card carrying its score for the ring.
     *  - empty: ranking ran but there are no matchable roles yet.
     */
    private function forYou(User $user, MatchService $matches, JobMatchRepository $repository): View
    {
        $profile = $user->candidateProfile;

        if (! $matches->profileIsScorable($profile)) {
            return view('candidate.for-you', [
                'state' => 'unscorable',
                'jobs' => $this->newestJobs(),
                'profile' => $profile,
            ]);
        }

        if (blank($profile->embedding)) {
            return view('candidate.for-you', [
                'state' => 'pending',
                'jobs' => $this->newestJobs(),
                'profile' => $profile,
            ]);
        }

        // Ranking is cached per profile embedding version (see
        // JobMatchRepository::cachedTopMatches) — a warm profile costs no
        // vector query at all. Each job's score is separately cached too
        // (deterministic, no LLM), so the whole feed is free on repeat views.
        $ranked = $repository->cachedTopMatches($profile, self::FEED_LIMIT)
            ->each(fn (Job $job) => $job->match_score = $matches->scoreFor($profile, $job))
            ->sortByDesc('match_score')
            ->values();

        return view('candidate.for-you', [
            'state' => $ranked->isEmpty() ? 'empty' : 'ranked',
            'jobs' => $ranked,
            'profile' => $profile,
        ]);
    }

    /**
     * @return Collection<int, Job>
     */
    private function newestJobs()
    {
        return Job::active()->with('company')->latest()->take(self::FEED_LIMIT)->get();
    }

    private function marketingHome(): View
    {
        $featured = Job::active()->with('company')->latest()->take(6)->get();

        $companies = Company::query()
            ->withCount(['jobs as active_jobs_count' => fn ($q) => $q->active()])
            ->whereHas('jobs', fn ($q) => $q->active())
            ->orderByDesc('active_jobs_count')
            ->take(8)
            ->get();

        $totalJobs = Job::active()->count();
        $totalCompanies = Company::count();
        $totalCandidates = User::where('role', 'candidate')->count();

        return view('home', compact(
            'featured',
            'companies',
            'totalJobs',
            'totalCompanies',
            'totalCandidates',
        ));
    }
}
