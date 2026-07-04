<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\AI\CoverLetterDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CandidateCoverLetterDraftController extends Controller
{
    /**
     * Generate an editable cover-letter draft for the signed-in candidate
     * applying to a given job. Fetched asynchronously from the apply form;
     * the candidate reviews and edits the draft themselves — this never
     * submits an application.
     */
    public function store(Request $request, CoverLetterDraftService $drafts): JsonResponse
    {
        // The "Draft with AI" button is hidden when AI is off; guard the endpoint too.
        abort_unless($drafts->isAvailable(), 404);

        // Validated manually (rather than $request->validate()) because the
        // app only renders JSON for validation failures under api/* — see
        // bootstrap/app.php's shouldRenderJsonWhen(). This endpoint is JSON
        // only, so a failure must stay JSON rather than redirect.
        $validator = Validator::make($request->all(), [
            'job_id' => ['required', 'integer', 'exists:job_listings,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'draft' => null], 422);
        }

        $job = Job::active()->with('company')->find($validator->validated()['job_id']);
        abort_unless($job !== null, 404);

        $profile = Auth::user()->candidateProfile;

        if (! $drafts->profileIsUsable($profile)) {
            return response()->json(['status' => 'incomplete_profile', 'draft' => null]);
        }

        // A draft already generated for this (profile version, job) pair is
        // served straight from cache — no API call, and no cap check, since
        // it costs nothing. Re-opening the same apply form is always free.
        if (($cached = $drafts->cachedDraft($profile, $job)) !== null) {
            return response()->json(['status' => 'ok', 'draft' => $cached]);
        }

        // Daily per-user cap. Cover letters have no rule-based fallback, so
        // over the cap we degrade to "write it yourself" rather than spend.
        if (! $drafts->withinDailyCap()) {
            return response()->json(['status' => 'rate_limited', 'draft' => null]);
        }

        try {
            $draft = $drafts->draft($profile, $job);
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Cover letter draft failed', [
                'user_id' => Auth::id(),
                'job_id' => $job->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'draft' => null], 502);
        }

        return response()->json(['status' => 'ok', 'draft' => $draft]);
    }
}
