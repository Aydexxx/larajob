<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable, structured result of parsing a resume into profile fields.
 *
 * Always well-formed regardless of what the model returned: every factory
 * path validates and coerces, so consumers never see missing keys or wrong
 * types. {@see empty()} is the degraded shape used when AI is disabled, the
 * model output was unusable, or nothing could be extracted — callers detect
 * it via {@see isEmpty()} and leave the profile untouched.
 *
 * @implements Arrayable<string, mixed>
 */
final class ResumeParseResult implements Arrayable, JsonSerializable
{
    /**
     * @param  array<int, string>  $skills
     * @param  array<int, string>  $links
     */
    public function __construct(
        public readonly ?string $headline,
        public readonly ?string $bio,
        public readonly array $skills,
        public readonly ?int $yearsOfExperience,
        public readonly ?string $location,
        public readonly array $links,
    ) {}

    public static function empty(): self
    {
        return new self(
            headline: null,
            bio: null,
            skills: [],
            yearsOfExperience: null,
            location: null,
            links: [],
        );
    }

    /**
     * Rebuild from a stored array (the suggested_profile column). Defensive
     * against shape drift: wrong types degrade to null/[] rather than throw.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            headline: self::stringOrNull($data['headline'] ?? null),
            bio: self::stringOrNull($data['bio'] ?? null),
            skills: self::stringList($data['skills'] ?? []),
            yearsOfExperience: self::yearsOrNull($data['years_of_experience'] ?? null),
            location: self::stringOrNull($data['location'] ?? null),
            links: self::stringList($data['links'] ?? []),
        );
    }

    public function isEmpty(): bool
    {
        return $this->headline === null
            && $this->bio === null
            && $this->skills === []
            && $this->yearsOfExperience === null
            && $this->location === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'bio' => $this->bio,
            'skills' => $this->skills,
            'years_of_experience' => $this->yearsOfExperience,
            'location' => $this->location,
            'links' => $this->links,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function yearsOrNull(mixed $value): ?int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        $years = (int) $value;

        return ($years >= 0 && $years <= 60) ? $years : null;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (string $item): string => trim($item))
            ->unique()
            ->values()
            ->all();
    }
}
