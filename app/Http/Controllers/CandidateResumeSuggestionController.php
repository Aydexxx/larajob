<?php

namespace App\Http\Controllers;

use App\Models\CandidateProfile;
use App\Services\AI\ResumeParseResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The review step between CV parsing and the live profile.
 *
 * Parsed resume data is only ever a *suggestion* (candidate_profiles.
 * suggested_profile). This controller shows each suggested field next to
 * the current value, lets the candidate tick and edit exactly what to
 * apply, and writes only those fields. Nothing is ever applied silently,
 * and dismissing discards the suggestion without touching the profile.
 */
class CandidateResumeSuggestionController extends Controller
{
    /**
     * Suggested-field key => live profile column. links is handled
     * separately (mapped to linkedin_url when one is a LinkedIn URL).
     */
    private const FIELD_MAP = [
        'headline' => 'headline',
        'bio' => 'bio',
        'skills' => 'skills',
        'years_of_experience' => 'experience_years',
        'location' => 'location',
        'linkedin_url' => 'linkedin_url',
    ];

    public function show(): View|RedirectResponse
    {
        $profile = Auth::user()->candidateProfile;

        if (! $profile?->hasPendingSuggestion()) {
            return redirect()->route('candidate.profile.edit');
        }

        $suggestion = ResumeParseResult::fromArray($profile->suggested_profile);

        return view('candidate.profile.resume-review', [
            'profile' => $profile,
            'suggestion' => $suggestion,
            'fields' => $this->reviewFields($profile, $suggestion),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $profile = Auth::user()->candidateProfile;

        if (! $profile?->hasPendingSuggestion()) {
            return redirect()->route('candidate.profile.edit');
        }

        $validated = $request->validate([
            'apply' => ['nullable', 'array'],
            'apply.*' => ['string', 'in:'.implode(',', array_keys(self::FIELD_MAP))],
            'values.headline' => ['nullable', 'string', 'max:255'],
            'values.bio' => ['nullable', 'string', 'max:2000'],
            'values.skills' => ['nullable', 'string', 'max:1000'],
            'values.years_of_experience' => ['nullable', 'integer', 'min:0', 'max:50'],
            'values.location' => ['nullable', 'string', 'max:255'],
            'values.linkedin_url' => ['nullable', 'url', 'max:255'],
        ]);

        $changes = [];

        foreach ($validated['apply'] ?? [] as $field) {
            $changes[self::FIELD_MAP[$field]] = $validated['values'][$field] ?? null;
        }

        if ($changes !== []) {
            // A normal (event-firing) save: applying a suggestion is a real
            // profile edit, so re-embedding is queued like any other update.
            $profile->update($changes);
        }

        $this->clearSuggestion($profile);

        return redirect()->route('candidate.profile.edit')->with('success', $changes === []
            ? 'No fields were applied. Your profile is unchanged.'
            : 'Your profile has been updated from your resume.');
    }

    public function destroy(): RedirectResponse
    {
        $profile = Auth::user()->candidateProfile;

        if ($profile?->hasPendingSuggestion()) {
            $this->clearSuggestion($profile);
        }

        return redirect()->route('candidate.profile.edit')
            ->with('success', 'Resume suggestions dismissed. Your profile is unchanged.');
    }

    /**
     * Build the per-field rows for the review form: only fields the parser
     * actually found, each with the current value for comparison and the
     * suggested value pre-filled for editing.
     *
     * @return array<string, array{label: string, current: mixed, suggested: string}>
     */
    private function reviewFields(CandidateProfile $profile, ResumeParseResult $suggestion): array
    {
        $linkedin = collect($suggestion->links)
            ->first(fn (string $link): bool => Str::contains(Str::lower($link), 'linkedin.com'));

        $rows = [
            'headline' => ['label' => 'Professional Headline', 'suggested' => $suggestion->headline],
            'bio' => ['label' => 'Bio', 'suggested' => $suggestion->bio],
            'skills' => ['label' => 'Skills', 'suggested' => $suggestion->skills === [] ? null : implode(', ', $suggestion->skills)],
            'years_of_experience' => ['label' => 'Years of Experience', 'suggested' => $suggestion->yearsOfExperience !== null ? (string) $suggestion->yearsOfExperience : null],
            'location' => ['label' => 'Location', 'suggested' => $suggestion->location],
            'linkedin_url' => ['label' => 'LinkedIn URL', 'suggested' => $linkedin],
        ];

        return collect($rows)
            ->filter(fn (array $row): bool => $row['suggested'] !== null)
            ->map(function (array $row, string $field) use ($profile): array {
                $row['current'] = $profile->{self::FIELD_MAP[$field]};

                return $row;
            })
            ->all();
    }

    private function clearSuggestion(CandidateProfile $profile): void
    {
        // saveQuietly: clearing a suggestion is bookkeeping, not a profile
        // edit — it must not queue re-embedding or bump updated_at.
        $profile->forceFill([
            'suggested_profile' => null,
            'suggested_at' => null,
        ])->saveQuietly();
    }
}
