<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\AI\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CandidateMatchController extends Controller
{
    /**
     * The explained match between the signed-in candidate's profile and a
     * job. Fetched asynchronously by the job page so a slow LLM call never
     * blocks the page render.
     *
     * Available with EVERY provider setting: with AI enabled the narrative
     * comes from the model; with AI_PROVIDER=none MatchService degrades to
     * a rule-based explanation (source: "rules") with no API call — so
     * this endpoint no longer 404s when AI is off. The result is cached by
     * (profile embedding version, job version); repeat calls are free.
     */
    public function show(Job $job, MatchService $matches): JsonResponse
    {
        abort_unless($job->status === 'active', 404);

        $profile = Auth::user()->candidateProfile;

        if (! $matches->profileIsScorable($profile)) {
            return response()->json(['status' => 'incomplete_profile', 'match' => null]);
        }

        return response()->json([
            'status' => 'ok',
            'match' => $matches->explain($profile, $job)->toArray(),
        ]);
    }
}
