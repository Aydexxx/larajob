<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates a slug guaranteed unique on a model's `slug` column, appending
 * -2, -3, ... on collision.
 *
 * Job titles and company names are free text that different records can
 * legitimately share (two employers both hiring a "Software Engineer"), but
 * `job_listings.slug` / `companies.slug` are unique columns used for route
 * binding. Slugifying the raw title/name without checking for collisions
 * throws a database-level unique-constraint violation (500) the moment two
 * records collide — this is the fix.
 */
class UniqueSlug
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  int|string|null  $ignoreId  Exclude this record's own id — pass
     *                                     the model being updated so keeping
     *                                     its title unchanged doesn't collide
     *                                     with itself.
     */
    public static function generate(string $modelClass, string $source, int|string|null $ignoreId = null): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $suffix = 2;

        while (
            $modelClass::query()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
