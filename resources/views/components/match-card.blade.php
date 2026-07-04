@props([
    'endpoint',
    'result' => null,
    'incomplete' => false,
    'profileUrl' => null,
])

{{--
    AI match-score card. Shared by the candidate job page and the employer
    application detail page. Render this only when AI is enabled — the caller
    decides that; this component assumes scoring is available.

    UX: never blocks the page. If a cached result is passed in ($result) it
    renders instantly; otherwise Alpine fetches it from $endpoint on init and
    shows a spinner meanwhile. A slow or failing AI call degrades to an inline
    message, never a broken page.
--}}
<div
    x-data="{
        loading: false,
        failed: false,
        incomplete: {{ $incomplete ? 'true' : 'false' }},
        match: {{ $result ? Illuminate\Support\Js::from($result->toArray()) : 'null' }},
        init() {
            if (this.incomplete || this.match) {
                return;
            }
            this.load();
        },
        load() {
            this.loading = true;
            this.failed = false;
            fetch('{{ $endpoint }}', { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => {
                    if (data.status === 'incomplete_profile') {
                        this.incomplete = true;
                    } else if (data.status === 'ok' && data.match) {
                        this.match = data.match;
                    } else {
                        this.failed = true;
                    }
                })
                .catch(() => { this.failed = true; })
                .finally(() => { this.loading = false; });
        },
        barClass() {
            const t = this.match ? this.match.tier : 'low';
            return t === 'high' ? 'bg-green-500' : t === 'medium' ? 'bg-yellow-500' : 'bg-gray-400';
        },
        textClass() {
            const t = this.match ? this.match.tier : 'low';
            return t === 'high' ? 'text-green-700' : t === 'medium' ? 'text-yellow-700' : 'text-gray-600';
        },
    }"
    x-init="init()"
    class="surface p-6"
>
    <div class="flex items-center gap-1.5 mb-4">
        <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
        <h3 class="font-bold text-gray-900">AI match</h3>
        <x-ui.badge color="brand" size="sm">Beta</x-ui.badge>
    </div>

    {{-- Incomplete profile --}}
    <template x-if="incomplete">
        <div class="text-sm text-gray-600">
            <p>Complete your profile to see how well you match this role.</p>
            @if ($profileUrl)
                <a href="{{ $profileUrl }}" class="inline-block mt-2 text-sm font-medium text-brand-600 hover:text-brand-700">
                    Complete profile &rarr;
                </a>
            @endif
        </div>
    </template>

    {{-- Loading --}}
    <template x-if="loading">
        <div class="flex items-center gap-2 text-sm text-gray-500 py-2">
            <svg class="animate-spin h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Scoring your match&hellip;
        </div>
    </template>

    {{-- Failure (subtle, retryable) --}}
    <template x-if="failed && !loading">
        <div class="text-sm text-gray-500 py-1">
            <p>Match score is unavailable right now.</p>
            <button type="button" @click="load()" class="mt-1 text-sm font-medium text-brand-600 hover:text-brand-700">
                Try again
            </button>
        </div>
    </template>

    {{-- Result --}}
    <template x-if="match && !loading">
        <div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold" :class="textClass()" x-text="match.percentage + '%'"></span>
                <span class="text-sm text-gray-500">match</span>
            </div>

            <div class="mt-2 h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                <div class="h-full rounded-full transition-all" :class="barClass()" :style="`width: ${match.percentage}%`"></div>
            </div>

            <p class="mt-3 text-sm text-gray-700" x-text="match.summary"></p>

            <template x-if="match.strengths && match.strengths.length">
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Strengths</p>
                    <ul class="space-y-1">
                        <template x-for="(item, i) in match.strengths" :key="'s' + i">
                            <li class="flex items-start gap-1.5 text-sm text-gray-700">
                                <svg class="h-4 w-4 text-green-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                <span x-text="item"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <template x-if="match.gaps && match.gaps.length">
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Gaps</p>
                    <ul class="space-y-1">
                        {{-- Gap items are strings (MatchResult, employer flow) or
                             {gap, suggestion} objects (MatchExplanation, candidate flow). --}}
                        <template x-for="(item, i) in match.gaps" :key="'g' + i">
                            <li class="flex items-start gap-1.5 text-sm text-gray-700">
                                <svg class="h-4 w-4 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                <span>
                                    <span x-text="typeof item === 'string' ? item : item.gap"></span>
                                    <template x-if="typeof item === 'object' && item.suggestion">
                                        <span class="block text-xs text-gray-500 mt-0.5" x-text="item.suggestion"></span>
                                    </template>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>
            </template>

            <p class="mt-4 text-2xs text-gray-400">AI-generated estimate — use as a guide, not a decision.</p>
        </div>
    </template>
</div>
