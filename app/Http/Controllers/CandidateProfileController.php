<?php

namespace App\Http\Controllers;

use App\Jobs\ParseResume;
use App\Models\CandidateProfile;
use App\Services\AI\AICostGuard;
use App\Services\Resume\ResumeStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CandidateProfileController extends Controller
{
    public function edit(): View
    {
        $profile = Auth::user()->candidateProfile ?? new CandidateProfile;

        return view('candidate.profile.edit', compact('profile'));
    }

    public function update(Request $request, ResumeStorage $resumes, AICostGuard $guard): RedirectResponse
    {
        $validated = $request->validate([
            'headline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'resume' => ResumeStorage::validationRules(),
        ]);

        $data = collect($validated)->except('resume')->toArray();

        $uploadedResume = $request->hasFile('resume');

        // Hash the upload before it is moved, to debounce re-parsing an
        // identical file (see afterResumeUpload()).
        $resumeHash = $uploadedResume
            ? hash_file('sha256', $request->file('resume')->getRealPath())
            : null;

        if ($uploadedResume) {
            // Replace: drop the previous file before storing the new one.
            // Query the column fresh rather than a possibly-cached relation.
            $resumes->delete(Auth::user()->candidateProfile()->value('resume_path'));
            $data['resume_path'] = $resumes->store($request->file('resume'));
        }

        $profile = CandidateProfile::updateOrCreate(
            ['user_id' => Auth::id()],
            $data
        );

        if ($uploadedResume) {
            return $this->afterResumeUpload($profile, (string) $resumeHash, $guard);
        }

        return redirect()->route('candidate.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Decide whether to (re-)analyze an uploaded resume. The profile is
     * already saved either way — only the LLM-backed parse is gated:
     *
     *  - the exact same file re-uploaded within the debounce window is
     *    skipped (no new parse, keep the earlier suggestion);
     *  - once the candidate hits their daily resume-analysis cap, further
     *    uploads store fine but aren't analyzed until tomorrow.
     *
     * Parsing itself still runs off the request cycle and never edits the
     * live profile — the result lands as a reviewable suggestion.
     */
    private function afterResumeUpload(CandidateProfile $profile, string $resumeHash, AICostGuard $guard): RedirectResponse
    {
        $redirect = redirect()->route('candidate.profile.edit');

        if ($guard->resumeParseDebounced($profile->id, $resumeHash)) {
            return $redirect->with('success', 'Profile updated. This resume was just analyzed — your earlier suggestions still apply.');
        }

        if (! $guard->allow('cv-parse')) {
            return $redirect->with('success', "Profile updated. You've reached today's resume-analysis limit, so this file wasn't analyzed — please try again tomorrow.");
        }

        $guard->hit('cv-parse');
        $guard->markResumeParsed($profile->id, $resumeHash);

        // Quiet: flips a progress flag, not a profile edit — must not queue
        // re-embedding or bump updated_at.
        $profile->forceFill(['resume_analyzing' => true])->saveQuietly();

        ParseResume::dispatch($profile->id);

        return $redirect->with('success', 'Profile updated. Your resume is being analyzed — suggestions to fill in your profile will appear here shortly.');
    }

    /**
     * Polled by the profile page while a resume analysis is in flight, so
     * the "analyzing" state can resolve into the review screen the moment
     * the queued ParseResume job finishes — without a manual refresh.
     */
    public function resumeStatus(): JsonResponse
    {
        $profile = Auth::user()->candidateProfile;

        return response()->json([
            'analyzing' => (bool) $profile?->isAnalyzingResume(),
            'ready' => (bool) $profile?->hasPendingSuggestion(),
        ]);
    }

    /**
     * View the candidate's own profile resume. Served via a signed, expiring
     * URL (S3/R2) or an app-streamed response (local); the file is never
     * publicly reachable.
     */
    public function resume(ResumeStorage $resumes): Response
    {
        $path = Auth::user()->candidateProfile()->value('resume_path');

        abort_unless(filled($path), 404);

        return $resumes->view($path, 'resume.pdf');
    }
}
