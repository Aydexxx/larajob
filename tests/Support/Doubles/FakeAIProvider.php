<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Services\AI\Contracts\AIProvider;

class FakeAIProvider implements AIProvider
{
    public int $embedCalls = 0;

    public int $chatCalls = 0;

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

    public function embed(string $text): array
    {
        $this->embedCalls++;

        return $this->vector;
    }

    public function chat(string $prompt, ?string $system = null): string
    {
        $this->chatCalls++;

        return $this->chatResponse;
    }
}
