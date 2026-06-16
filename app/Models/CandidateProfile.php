<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
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
class CandidateProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
        ];
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
}
