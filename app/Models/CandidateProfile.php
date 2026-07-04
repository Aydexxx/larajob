<?php

namespace App\Models;

use App\Observers\CandidateProfileObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'headline',
    'bio',
    'skills',
    'experience_years',
    'phone',
    'location',
    'linkedin_url',
    'resume_path',
])]
#[ObservedBy(CandidateProfileObserver::class)]
class CandidateProfile extends Model
{
    use HasFactory;

    /**
     * Fields that feed the embedding input. Changing any of these on update
     * should trigger re-embedding; see CandidateProfileObserver.
     */
    public const EMBEDDABLE_FIELDS = ['headline', 'bio', 'skills', 'experience_years', 'location'];

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'embedding' => 'array',
            'embedded_at' => 'datetime',
            'suggested_profile' => 'array',
            'suggested_at' => 'datetime',
            'resume_analyzing' => 'boolean',
        ];
    }

    /**
     * Whether a resume-parse suggestion is waiting for the candidate's
     * review. The suggestion may be empty (nothing extractable) — the
     * review screen handles that case with a friendly message.
     */
    public function hasPendingSuggestion(): bool
    {
        return $this->suggested_profile !== null;
    }

    /**
     * Whether a dispatched ParseResume job hasn't finished yet. Set true
     * when the job is queued and cleared by the job itself on completion —
     * see ParseResume::handle().
     *
     * Coerced to bool: the value is null on an unpersisted profile (the edit
     * page instantiates `new CandidateProfile` for candidates without a row
     * yet) and on legacy rows written before the column had a default, and
     * the 'boolean' cast passes null through untouched — so the raw attribute
     * can be null even with the cast in place.
     */
    public function isAnalyzingResume(): bool
    {
        return (bool) $this->resume_analyzing;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completionPercent(): int
    {
        $fields = ['headline', 'bio', 'skills', 'experience_years', 'location', 'resume_path'];
        $filled = collect($fields)->filter(fn ($f) => ! empty($this->$f))->count();

        return (int) round(($filled / count($fields)) * 100);
    }

    /**
     * A version stamp that only moves when the embedding was regenerated
     * (i.e. a field that feeds matching actually changed — see
     * EMBEDDABLE_FIELDS / CandidateProfileObserver). Used as the cache-key
     * ingredient everywhere a match result is cached "per profile version",
     * so unrelated edits (phone, LinkedIn) never invalidate a warm cache,
     * but a re-embed always does. Falls back to updated_at before any
     * embedding exists (e.g. AI_PROVIDER=none pre-backfill).
     */
    public function embeddingVersion(): int
    {
        return optional($this->embedded_at)->timestamp
            ?? optional($this->updated_at)->timestamp
            ?? 0;
    }
}
