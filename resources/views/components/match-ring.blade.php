@props([
    'score' => null,      // int 0-100, or null when unknown/unscorable
    'size' => 'md',       // sm | md | lg
    'label' => false,     // show the word "match" under the number
    'source' => null,     // 'model' | 'rules' (informational only)
])

@php
    $hasScore = is_numeric($score);
    $value = $hasScore ? max(0, min(100, (int) $score)) : 0;

    // Tier thresholds mirror MatchExplanation::tier() so the ring and the
    // written explanation never disagree. High match glows in the brand
    // signature colour — the identity and "great match" are one hue.
    $tier = match (true) {
        ! $hasScore => 'none',
        $value >= 75 => 'high',
        $value >= 50 => 'medium',
        default => 'low',
    };

    $tone = [
        'high'   => ['text' => 'text-brand-600', 'track' => 'text-brand-100'],
        'medium' => ['text' => 'text-accent-500', 'track' => 'text-accent-100'],
        'low'    => ['text' => 'text-gray-400', 'track' => 'text-gray-200'],
        'none'   => ['text' => 'text-gray-300', 'track' => 'text-gray-200'],
    ][$tier];

    // Geometry per size: [box, stroke, number text class, caption text class].
    [$box, $stroke, $numClass, $capClass] = [
        'sm' => [44, 4, 'text-xs font-bold', 'text-[9px]'],
        'md' => [60, 5, 'text-base font-extrabold', 'text-[10px]'],
        'lg' => [132, 9, 'text-4xl font-extrabold', 'text-xs'],
    ][$size] ?? [60, 5, 'text-base font-extrabold', 'text-[10px]'];

    $r = ($box - $stroke) / 2;
    $circumference = 2 * M_PI * $r;
    $offset = $circumference * (1 - $value / 100);
    $center = $box / 2;

    $ariaLabel = $hasScore
        ? "Match score {$value} out of 100"
        : 'Match score not available yet';
@endphp

<span
    {{ $attributes->merge(['class' => 'relative inline-flex items-center justify-center shrink-0']) }}
    style="width: {{ $box }}px; height: {{ $box }}px;"
    role="img"
    aria-label="{{ $ariaLabel }}"
>
    <svg class="absolute inset-0 -rotate-90" width="{{ $box }}" height="{{ $box }}" viewBox="0 0 {{ $box }} {{ $box }}" fill="none" aria-hidden="true">
        {{-- Track --}}
        <circle
            cx="{{ $center }}" cy="{{ $center }}" r="{{ $r }}"
            class="{{ $tone['track'] }}"
            stroke="currentColor" stroke-width="{{ $stroke }}"
            @unless($hasScore) stroke-dasharray="2 4" @endunless
        />
        {{-- Progress arc --}}
        @if ($hasScore)
            <circle
                cx="{{ $center }}" cy="{{ $center }}" r="{{ $r }}"
                class="{{ $tone['text'] }} transition-[stroke-dashoffset] duration-700 ease-out"
                stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $offset }}"
            />
        @endif
    </svg>

    <span class="relative flex flex-col items-center leading-none {{ $tone['text'] }}">
        @if ($hasScore)
            <span class="{{ $numClass }}">{{ $value }}<span class="text-[0.6em] font-bold align-top">%</span></span>
            @if ($label)
                <span class="{{ $capClass }} font-semibold uppercase tracking-wide text-gray-400 mt-0.5">match</span>
            @endif
        @else
            <svg class="h-1/3 w-1/3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
        @endif
    </span>
</span>
