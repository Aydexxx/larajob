<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Services\AI\Contracts\AIProvider;

class FakeAIProvider implements AIProvider
{
    public int $embedCalls = 0;

    public int $chatCalls = 0;

    public ?string $lastPrompt = null;

    public ?string $lastSystem = null;

    /**
     * Per-feature call tally, so tests can assert cost attribution.
     *
     * @var array<string, int>
     */
    public array $featureCalls = [];

    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly array $vector = [0.1, 0.2, 0.3],
        private readonly string $chatResponse = '',
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function embed(string $text, string $feature = 'embedding'): array
    {
        $this->embedCalls++;
        $this->featureCalls[$feature] = ($this->featureCalls[$feature] ?? 0) + 1;

        return $this->vector;
    }

    public function chat(string $prompt, ?string $system = null, string $feature = 'chat'): string
    {
        $this->chatCalls++;
        $this->lastPrompt = $prompt;
        $this->lastSystem = $system;
        $this->featureCalls[$feature] = ($this->featureCalls[$feature] ?? 0) + 1;

        return $this->chatResponse;
    }
}
