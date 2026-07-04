<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Services\AI\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class EmployerJobApplicantsController extends Controller
{
    /**
     * Ranked applicants for a single job, owner-only. Ordering uses the
     * deterministic, cached, LLM-free match score ({@see MatchService::scoreFor()})
     * so ranking a whole list costs no tokens and works with AI disabled
     * (it degrades to structured skill-overlap). The one-line AI summary per
     * candidate is fetched lazily by the page — see {@see summary()}.
     */
    public function index(Job $job, MatchService $matches): View
    {
        $this->authorize('viewApplicants', $job);

        $job->load('company');

        $applications = Application::where('job_id', $job->id)
            ->with('user.candidateProfile')
            ->latest()
            ->get();

        $this->attachScores($applications, $job, $matches);

        $ranked = $applications
            ->sortByDesc(fn (Application $a) => $a->match_score ?? -1)
            ->values();

        $aiEnabled = $matches->isAvailable();

        return view('employer.jobs.applicants', compact('job', 'ranked', 'aiEnabled'));
    }

    /**
     * On-demand one-sentence match summary (plus its reason) for a single
     * applicant, fetched asynchronously by the ranking page so a slow LLM
     * call never blocks the render. Reuses the cached match explanation:
     * with AI on the summary is model-written, with AI off it degrades to
     * the rule-based narrative — same contract as CandidateMatchController.
     */
    public function summary(Job $job, Application $application, MatchService $matches): JsonResponse
    {
        $this->authorize('viewAsEmployer', $application);
        abort_unless($application->job_id === $job->id, 404);

        $application->loadMissing('user.candidateProfile');
        $profile = $application->user?->candidateProfile;

        if (! $matches->profileIsScorable($profile)) {
            return response()->json(['status' => 'incomplete_profile', 'summary' => null]);
        }

        $explanation = $matches->explain($profile, $job);

        return response()->json([
            'status' => 'ok',
            'summary' => [
                'score' => $explanation->score,
                'sentence' => $explanation->summary,
                'reason' => $explanation->strengths[0] ?? null,
                'source' => $explanation->source,
            ],
        ]);
    }

    /**
     * Attach the deterministic match score (0-100, or null when the profile
     * can't be scored) to each application for ranking and display.
     *
     * @param  Collection<int, Application>  $applications
     */
    private function attachScores(Collection $applications, Job $job, MatchService $matches): void
    {
        $applications->each(function (Application $application) use ($job, $matches): void {
            $profile = $application->user?->candidateProfile;

            $application->match_score = $matches->profileIsScorable($profile)
                ? $matches->scoreFor($profile, $job)
                : null;
        });
    }
}
