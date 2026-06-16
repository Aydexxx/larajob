<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['job_id', 'user_id', 'cover_letter', 'resume_path', 'status'])]
class Application extends Model
{
    use HasFactory;
    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * The job listing this application was submitted for.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * The user (candidate) who submitted the application.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
