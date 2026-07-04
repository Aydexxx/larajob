<?php

namespace App\Http\Controllers;

use App\Services\AI\BiasCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class JobBiasCheckController extends Controller
{
    /**
     * Flag exclusionary / gendered phrasing in job-description text and
     * suggest neutral rewrites. Fetched asynchronously from the job create
     * form. Works in every provider state — the service returns model flags
     * when AI is enabled and a keyword-based scan when it is off — so this
     * endpoint never 404s on the AI setting.
     */
    public function store(Request $request, BiasCheckService $bias): JsonResponse
    {
        // Validated manually so a failure stays JSON rather than redirect —
        // see bootstrap/app.php's shouldRenderJsonWhen().
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'max:8000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'result' => null], 422);
        }

        try {
            $result = $bias->check($validator->validated()['text']);
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Bias check failed', [
                'user_id' => Auth::id(),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'result' => null], 502);
        }

        return response()->json(['status' => 'ok', 'result' => $result->toArray()]);
    }
}
