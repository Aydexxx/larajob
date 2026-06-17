<?php

namespace App\Http\Controllers;

use App\Services\AI\JobDescriptionDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EmployerJobDescriptionDraftController extends Controller
{
    /**
     * Generate an editable description + requirements draft from a job
     * title and a few bullet points. Fetched asynchronously from the job
     * create form; the employer reviews and edits the draft before
     * posting — this never saves a job listing.
     */
    public function store(Request $request, JobDescriptionDraftService $drafts): JsonResponse
    {
        // The "Generate description" button is hidden when AI is off; guard the endpoint too.
        abort_unless($drafts->isAvailable(), 404);

        // Validated manually (rather than $request->validate()) because the
        // app only renders JSON for validation failures under api/* — see
        // bootstrap/app.php's shouldRenderJsonWhen(). This endpoint is JSON
        // only, so a failure must stay JSON rather than redirect.
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'bullets' => ['required', 'array', 'min:1'],
            'bullets.*' => ['string', 'max:300'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'draft' => null], 422);
        }

        $data = $validator->validated();

        $bullets = array_values(array_filter(
            array_map('trim', $data['bullets']),
            fn (string $bullet): bool => $bullet !== '',
        ));

        if ($bullets === []) {
            return response()->json(['status' => 'error', 'draft' => null], 422);
        }

        try {
            $draft = $drafts->draft($data['title'], $bullets);
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Job description draft failed', [
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'draft' => null], 502);
        }

        return response()->json(['status' => 'ok', 'draft' => $draft]);
    }
}
