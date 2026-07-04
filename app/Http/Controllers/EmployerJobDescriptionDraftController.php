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
     * Generate an editable description + requirements draft from structured
     * inputs (title, seniority, must-have skills, location, salary band).
     * Fetched asynchronously from the job create form; the employer reviews
     * and edits the draft before posting — this never saves a job listing.
     *
     * Works in every provider state: the service returns a model draft when
     * AI is enabled and a deterministic template (no API call) when it is
     * off, so the endpoint never 404s on the AI setting.
     */
    public function store(Request $request, JobDescriptionDraftService $drafts): JsonResponse
    {
        // Validated manually (rather than $request->validate()) because the
        // app only renders JSON for validation failures under api/* — see
        // bootstrap/app.php's shouldRenderJsonWhen(). This endpoint is JSON
        // only, so a failure must stay JSON rather than redirect.
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'seniority' => ['nullable', 'string', 'max:100'],
            'skills' => ['nullable', 'array', 'max:20'],
            'skills.*' => ['string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'salary' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'draft' => null], 422);
        }

        $data = $validator->validated();

        $skills = array_values(array_filter(
            array_map('trim', $data['skills'] ?? []),
            fn (string $skill): bool => $skill !== '',
        ));

        try {
            $draft = $drafts->draft([
                'title' => trim($data['title']),
                'seniority' => $data['seniority'] ?? null,
                'skills' => $skills,
                'location' => $data['location'] ?? null,
                'salary' => $data['salary'] ?? null,
            ]);
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
