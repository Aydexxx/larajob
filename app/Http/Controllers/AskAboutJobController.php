<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Services\AI\AskAboutJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AskAboutJobController extends Controller
{
    /**
     * Answer a candidate's question about a single job listing. Public and
     * unauthenticated — a job's listing and company profile are already
     * public on the show page, so no additional data is exposed here.
     *
     * Stays up even when AI is disabled: AskAboutJobService returns a
     * fixed, honest message instead of the endpoint 404ing, so the chat
     * widget behaves predictably in every provider state.
     */
    public function store(Request $request, Job $job, AskAboutJobService $ask): JsonResponse
    {
        abort_unless($job->status === 'active', 404);

        // Validated manually (not $request->validate()) so a failure stays
        // JSON instead of redirecting — this route isn't under api/*, see
        // bootstrap/app.php's shouldRenderJsonWhen().
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:500'],
            // Not capped at the ~6-turn limit here — AskAboutJobService is
            // the source of truth for that; this only bounds payload abuse.
            'history' => ['array', 'max:50'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'answer' => null], 422);
        }

        $data = $validator->validated();

        $answer = $ask->ask($job, $data['history'] ?? [], trim($data['question']));

        return response()->json(['status' => 'ok', 'answer' => $answer]);
    }
}
