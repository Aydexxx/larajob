<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\AI\MatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CandidateMatchController extends Controller
{
    /**
     * Compute (or return cached) match between the signed-in candidate's
     * profile and a job. Fetched asynchronously by the job page so a slow
     * LLM call never blocks the page render.
     */
    public function show(Job $job, MatchService $matches): JsonResponse
    {
        // The whole match UI is hidden when AI is off; guard the endpoint too.
        abort_unless($matches->isAvailable(), 404);
        abort_unless($job->status === 'active', 404);

        $profile = Auth::user()->candidateProfile;

        if (! $matches->profileIsScorable($profile)) {
            return response()->json(['status' => 'incomplete_profile', 'match' => null]);
        }

        return response()->json([
            'status' => 'ok',
            'match' => $matches->score($profile, $job)->toArray(),
        ]);
    }
}
