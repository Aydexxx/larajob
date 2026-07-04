@props([
    'job',
    'layout' => 'grid',
    'matchScore' => null,       // int 0-100 → renders the match ring
    'matchEndpoint' => null,    // URL to lazily fetch top strength/gap on hover
])

@php
    $company = $job->company;
    $logo = $company?->logo ? Storage::url($company->logo) : null;
    $hasMatch = is_numeric($matchScore);

    $salary = null;
    if ($job->salary_min && $job->salary_max) {
        $salary = '$' . number_format($job->salary_min / 1000) . 'k – $' . number_format($job->salary_max / 1000) . 'k';
    } elseif ($job->salary_min) {
        $salary = 'From $' . number_format($job->salary_min / 1000) . 'k';
    } elseif ($job->salary_max) {
        $salary = 'Up to $' . number_format($job->salary_max / 1000) . 'k';
    }

    // Alpine state powering the lazy hover/focus reveal of AI reasoning.
    // Fetches once, only when the candidate actually engages the card, so a
    // list of N cards costs zero tokens until intent is shown.
    $alpine = $hasMatch && $matchEndpoint
        ? "{ open: false, loaded: false, strength: null, gap: null,
             reveal() {
                 this.open = true;
                 if (this.loaded) return;
                 this.loaded = true;
                 fetch('{$matchEndpoint}', { headers: { Accept: 'application/json' } })
                     .then(r => r.ok ? r.json() : Promise.reject(r))
                     .then(d => {
                         if (d.status === 'ok' && d.match) {
                             this.strength = (d.match.strengths || [])[0] || null;
                             const g = (d.match.gaps || [])[0];
                             this.gap = g ? (typeof g === 'string' ? g : g.gap) : null;
                         }
                     })
                     .catch(() => {});
             } }"
        : null;
@endphp

@if ($layout === 'row')
    <a href="{{ route('jobs.show', $job) }}"
        @if ($alpine) x-data="{{ $alpine }}" @mouseenter="reveal()" @focus="reveal()" @endif
        {{ $attributes->merge(['class' => 'surface group block p-5 transition-all duration-200 hover:shadow-elevated hover:border-brand-200 motion-safe:hover:-translate-y-0.5']) }}>
        <div class="flex items-start gap-4">
            <x-ui.avatar :name="$company?->name ?? '?'" :src="$logo" size="md" />

            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 leading-snug group-hover:text-brand-700 transition-colors">
                            {{ $job->title }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-0.5 truncate">{{ $company?->name }}</p>
                    </div>

                    @if ($hasMatch)
                        <x-match-ring :score="$matchScore" size="md" class="shrink-0" />
                    @else
                        <span class="text-xs text-gray-400 shrink-0 whitespace-nowrap">
                            {{ $job->created_at->diffForHumans() }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <x-ui.badge :status="$job->type" />
                    @if ($job->is_remote)
                        <x-ui.badge color="green" dot>Remote</x-ui.badge>
                    @endif
                    @if ($job->location)
                        <x-ui.badge color="gray">{{ $job->location }}</x-ui.badge>
                    @endif
                    @if ($salary)
                        <x-ui.badge color="accent">{{ $salary }}</x-ui.badge>
                    @endif
                </div>

                @if ($alpine)
                    {{-- Lazy AI reasoning, revealed on hover/focus --}}
                    <div x-cloak x-show="open && (strength || gap)"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="mt-3 pt-3 border-t border-gray-100 grid gap-1.5 text-sm">
                        <template x-if="strength">
                            <p class="flex items-start gap-1.5 text-gray-600">
                                <svg class="h-4 w-4 text-brand-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                <span x-text="strength"></span>
                            </p>
                        </template>
                        <template x-if="gap">
                            <p class="flex items-start gap-1.5 text-gray-500">
                                <svg class="h-4 w-4 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                <span x-text="gap"></span>
                            </p>
                        </template>
                    </div>
                @endif
            </div>
        </div>
    </a>
@else
    <a href="{{ route('jobs.show', $job) }}"
        @if ($alpine) x-data="{{ $alpine }}" @mouseenter="reveal()" @focus="reveal()" @endif
        {{ $attributes->merge(['class' => 'surface group flex flex-col p-5 h-full transition-all duration-200 hover:shadow-elevated hover:border-brand-200 motion-safe:hover:-translate-y-0.5']) }}>
        <div class="flex items-start justify-between gap-3 mb-4">
            <div class="flex items-center gap-3 min-w-0">
                <x-ui.avatar :name="$company?->name ?? '?'" :src="$logo" size="sm" />
                <p class="text-sm text-gray-500 truncate">{{ $company?->name }}</p>
            </div>
            @if ($hasMatch)
                <x-match-ring :score="$matchScore" size="sm" class="shrink-0 -mt-1 -mr-1" />
            @endif
        </div>

        <h3 class="font-semibold text-gray-900 leading-snug mb-3 group-hover:text-brand-700 transition-colors">
            {{ $job->title }}
        </h3>

        <div class="flex flex-wrap gap-2 mb-4">
            <x-ui.badge :status="$job->type" />
            @if ($job->is_remote)
                <x-ui.badge color="green" dot>Remote</x-ui.badge>
            @endif
            @if ($job->location)
                <x-ui.badge color="gray">{{ $job->location }}</x-ui.badge>
            @endif
        </div>

        @if ($alpine)
            <div x-cloak x-show="open && strength"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mb-4 -mt-1">
                <p class="flex items-start gap-1.5 text-sm text-gray-600">
                    <svg class="h-4 w-4 text-brand-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    <span x-text="strength"></span>
                </p>
            </div>
        @endif

        <div class="flex items-center justify-between text-xs mt-auto pt-3 border-t border-gray-100">
            <span class="font-semibold text-gray-700">{{ $salary ?? 'Competitive' }}</span>
            <span class="text-gray-400">{{ $job->created_at->diffForHumans() }}</span>
        </div>
    </a>
@endif
