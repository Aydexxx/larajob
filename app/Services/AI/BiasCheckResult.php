<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use JsonSerializable;

/**
 * Immutable result of a bias check on job-description text: a list of flagged
 * phrases, each paired with what's wrong and a suggested neutral rewrite.
 *
 * `source` records how the flags were produced: 'model' when an LLM found
 * them, 'rules' when the deterministic keyword scan did (AI disabled or model
 * output unusable). An empty flag list means nothing was flagged — a clean
 * pass, not a failure.
 *
 * @implements Arrayable<string, mixed>
 */
final class BiasCheckResult implements Arrayable, JsonSerializable
{
    public const SOURCE_MODEL = 'model';

    public const SOURCE_RULES = 'rules';

    private const MAX_FLAGS = 20;

    /**
     * @param  array<int, array{phrase: string, issue: string, suggestion: string}>  $flags
     */
    public function __construct(
        public readonly array $flags,
        public readonly string $source,
    ) {}

    /**
     * Build from a raw (possibly model-produced) value, keeping only
     * well-formed flags and capping the count. Wrong types are dropped
     * rather than throwing.
     */
    public static function fromArray(mixed $flags, string $source): self
    {
        return new self(
            flags: self::flagList($flags),
            source: $source === self::SOURCE_MODEL ? self::SOURCE_MODEL : self::SOURCE_RULES,
        );
    }

    public function isEmpty(): bool
    {
        return $this->flags === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'flags' => $this->flags,
            'source' => $this->source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<int, array{phrase: string, issue: string, suggestion: string}>
     */
    private static function flagList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $phrase = is_string($item['phrase'] ?? null) ? trim($item['phrase']) : '';

                if ($phrase === '') {
                    return null;
                }

                return [
                    'phrase' => Str::limit($phrase, 120),
                    'issue' => is_string($item['issue'] ?? null) ? Str::limit(trim($item['issue']), 200) : '',
                    'suggestion' => is_string($item['suggestion'] ?? null) ? Str::limit(trim($item['suggestion']), 200) : '',
                ];
            })
            ->filter()
            ->take(self::MAX_FLAGS)
            ->values()
            ->all();
    }
}
