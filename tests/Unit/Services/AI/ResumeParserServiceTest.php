<?php

namespace Tests\Unit\Services\AI;

use App\Services\AI\Contracts\AIProvider;
use App\Services\AI\ResumeParserService;
use Tests\Support\Doubles\FakeAIProvider;
use Tests\TestCase;

class ResumeParserServiceTest extends TestCase
{
    private function serviceWith(FakeAIProvider $fake): ResumeParserService
    {
        $this->app->instance(AIProvider::class, $fake);

        return $this->app->make(ResumeParserService::class);
    }

    private function resumeText(): string
    {
        return file_get_contents(base_path('tests/Support/fixtures/resume.txt'));
    }

    public function test_returns_empty_result_without_calling_the_model_when_ai_is_disabled(): void
    {
        $fake = new FakeAIProvider(enabled: false);

        $result = $this->serviceWith($fake)->parse($this->resumeText());

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $fake->chatCalls);

        // The empty result is still fully structured, so the flow can store
        // and render it without special-casing.
        $this->assertSame([
            'headline' => null,
            'bio' => null,
            'skills' => [],
            'years_of_experience' => null,
            'location' => null,
            'links' => [],
        ], $result->toArray());
    }

    public function test_parses_a_strict_json_model_response_into_a_structured_result(): void
    {
        $fake = new FakeAIProvider(chatResponse: json_encode([
            'headline' => 'Senior Backend Engineer',
            'bio' => 'Experienced PHP and Laravel engineer with 8 years building web platforms.',
            'skills' => ['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker'],
            'years_of_experience' => 8,
            'location' => 'Istanbul, Turkey',
            'links' => ['https://www.linkedin.com/in/janedev'],
        ]));

        $result = $this->serviceWith($fake)->parse($this->resumeText());

        $this->assertFalse($result->isEmpty());
        $this->assertSame('Senior Backend Engineer', $result->headline);
        $this->assertSame(['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker'], $result->skills);
        $this->assertSame(8, $result->yearsOfExperience);
        $this->assertSame('Istanbul, Turkey', $result->location);
        $this->assertSame(['https://www.linkedin.com/in/janedev'], $result->links);
        $this->assertSame(1, $fake->chatCalls);
    }

    public function test_tolerates_markdown_fences_and_prose_around_the_json(): void
    {
        $fake = new FakeAIProvider(chatResponse: "Here is the extraction:\n```json\n{\"headline\": \"Senior Backend Engineer\", \"skills\": [\"PHP\"]}\n```\nLet me know if you need more.");

        $result = $this->serviceWith($fake)->parse($this->resumeText());

        $this->assertSame('Senior Backend Engineer', $result->headline);
        $this->assertSame(['PHP'], $result->skills);
    }

    public function test_malformed_json_degrades_to_an_empty_result_without_throwing(): void
    {
        $fake = new FakeAIProvider(chatResponse: 'Sorry, I cannot produce JSON { "headline": "Broken');

        $result = $this->serviceWith($fake)->parse($this->resumeText());

        $this->assertTrue($result->isEmpty());
    }

    public function test_wrong_types_in_the_response_are_coerced_defensively(): void
    {
        $fake = new FakeAIProvider(chatResponse: json_encode([
            'headline' => ['not', 'a', 'string'],
            'bio' => '   ',
            'skills' => ['PHP', 42, '', '  Laravel  ', 'PHP'],
            'years_of_experience' => 'about ten',
            'location' => 12345,
            'links' => 'https://not-a-list.example',
        ]));

        $result = $this->serviceWith($fake)->parse($this->resumeText());

        $this->assertNull($result->headline);
        $this->assertNull($result->bio);
        $this->assertSame(['PHP', 'Laravel'], $result->skills);
        $this->assertNull($result->yearsOfExperience);
        $this->assertNull($result->location);
        $this->assertSame([], $result->links);
    }

    public function test_blank_resume_text_short_circuits_without_calling_the_model(): void
    {
        $fake = new FakeAIProvider(chatResponse: '{"headline": "Should never be used"}');

        $result = $this->serviceWith($fake)->parse("   \n\t  ");

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $fake->chatCalls);
    }
}
