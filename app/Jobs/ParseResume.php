<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CandidateProfile;
use App\Services\AI\ResumeParserService;
use App\Services\Resume\PdfTextExtractor;
use App\Services\Resume\ResumeStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Parses an uploaded resume PDF into a profile suggestion, off the request
 * cycle like every other AI-adjacent operation.
 *
 * The outcome is always a stored suggested_profile — even the empty shape
 * when AI is disabled, the PDF has no text layer, or the model output was
 * unusable — so the review screen can distinguish "analysis finished with
 * nothing found" (friendly message) from "no analysis pending" (no
 * suggestion row at all). The live profile fields are never written here;
 * that only happens when the candidate explicitly applies the suggestion.
 */
class ParseResume implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $profileId,
    ) {}

    public function handle(PdfTextExtractor $extractor, ResumeParserService $parser, ResumeStorage $resumes): void
    {
        $profile = CandidateProfile::find($this->profileId);

        if (! $profile || blank($profile->resume_path)) {
            return;
        }

        $result = $parser->parse($this->extractText($extractor, $resumes, $profile));

        // saveQuietly: a suggestion is not a profile edit — it must not
        // re-fire model events (embedding re-generation) or bump the
        // profile's updated_at used in match cache keys.
        $profile->forceFill([
            'suggested_profile' => $result->toArray(),
            'suggested_at' => now(),
            'resume_analyzing' => false,
        ])->saveQuietly();

        Log::channel('ai')->info('Resume parsed into profile suggestion', [
            'profile_id' => $profile->id,
            'empty' => $result->isEmpty(),
        ]);
    }

    /**
     * Read and extract the resume text, degrading to '' (→ empty
     * suggestion) on unreadable or text-less files rather than failing the
     * queue job — a corrupt upload is a user-content problem, not a retry
     * candidate.
     */
    private function extractText(PdfTextExtractor $extractor, ResumeStorage $resumes, CandidateProfile $profile): string
    {
        try {
            $contents = $resumes->get($profile->resume_path);

            return $contents === null ? '' : $extractor->extract($contents);
        } catch (Throwable $e) {
            Log::channel('ai')->warning('Resume text extraction failed', [
                'profile_id' => $profile->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
