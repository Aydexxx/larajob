<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('job_listings')]
#[Fillable([
    'company_id',
    'title',
    'slug',
    'description',
    'requirements',
    'salary_min',
    'salary_max',
    'location',
    'is_remote',
    'type',
    'status',
    'expires_at',
])]
class Job extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salary_min' => 'integer',
            'salary_max' => 'integer',
            'is_remote' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The company that posted the job.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The applications submitted for this job.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
