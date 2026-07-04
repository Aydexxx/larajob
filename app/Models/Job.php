<?php

namespace App\Models;

use App\Observers\JobObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
#[ObservedBy(JobObserver::class)]
class Job extends Model
{
    use HasFactory;

    /**
     * Fields that feed the embedding input. Changing any of these on update
     * should trigger re-embedding; see JobObserver.
     */
    public const EMBEDDABLE_FIELDS = ['title', 'description', 'requirements', 'location', 'type'];

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
            'embedding' => 'array',
            'embedded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereLike('title', "%{$term}%")
                ->orWhereLike('description', "%{$term}%");
        });
    }

    public function scopeFilterType(Builder $query, array $types): Builder
    {
        if (empty($types)) {
            return $query;
        }

        return $query->whereIn('type', $types);
    }

    public function scopeRemote(Builder $query): Builder
    {
        return $query->where('is_remote', true);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status) {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Restrict to jobs that have a generated embedding. Used to build the
     * candidate set for semantic ranking without loading the whole table.
     */
    public function scopeHasEmbedding(Builder $query): Builder
    {
        return $query->whereNotNull('embedding');
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
