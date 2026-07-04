@props([
    'endpoint',
    'result' => null,       // MatchExplanation|null — cached, for instant render
    'incomplete' => false,
    'profileUrl' => null,
])

{{--
    Prominent AI match section for the candidate job page. Unlike the compact
    match-card, this is a full-width hero band: a large score ring beside the
    written strengths and actionable gaps. Never blocks the page — a cached
    result renders instantly; otherwise Alpine fetches on init with a skeleton,
    and any failure degrades to a quiet retry.
--}}
<section
    x-data="{
        loading: false,
        failed: false,
        incomplete: {{ $incomplete ? 'true' : 'false' }},
        match: {{ $result ? Illuminate\Support\Js::from($result->toArray()) : 'null' }},
        init() {
            if (this.incomplete || this.match) return;
            this.load();
        },
        load() {
            this.loading = true;
            this.failed = false;
            fetch('{{ $endpoint }}', { headers: { Accept: 'application/json' } })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((data) => {
                    if (data.status === 'incomplete_profile') this.incomplete = true;
                    else if (data.status === 'ok' && data.match) this.match = data.match;
                    else this.failed = true;
                })
                .catch(() => { this.failed = true; })
                .finally(() => { this.loading = false; });
        },
        ringStroke() {
            const t = this.match ? this.match.tier : 'low';
            return t === 'high' ? 'text-brand-600' : t === 'medium' ? 'text-accent-500' : 'text-gray-400';
        },
        ringTrack() {
            const t = this.match ? this.match.tier : 'low';
            return t === 'high' ? 'text-brand-100' : t === 'medium' ? 'text-accent-100' : 'text-gray-200';
        },
        dashoffset() {
            const c = 2 * Math.PI * 57;
            const v = this.match ? Math.max(0, Math.min(100, this.match.score ?? this.match.percentage)) : 0;
            return c * (1 - v / 100);
        },
    }"
    x-init="init()"
    class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white shadow-soft"
>
    <div class="absolute -top-16 -right-10 h-40 w-40 rounded-full bg-brand-200/30 blur-3xl" aria-hidden="true"></div>

    <div class="relative p-6 sm:p-7">
        <div class="flex items-center gap-2 mb-5">
            <span class="eyebrow">Your match</span>
            <x-ui.badge color="brand" size="sm" x-show="match && match.source === 'rules'" x-cloak>Rule-based</x-ui.badge>
        </div>

        {{-- Incomplete profile --}}
        <template x-if="incomplete">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <x-match-ring :score="null" size="lg" />
                <div>
                    <p class="font-semibold text-gray-900">See how well you fit this role</p>
                    <p class="text-sm text-gray-600 mt-1">Complete your profile and we'll score your match against every requirement.</p>
                    @if ($profileUrl)
                        <a href="{{ $profileUrl }}" class="inline-flex items-center gap-1 mt-3 text-sm font-semibold text-brand-600 hover:text-brand-700">
                            Complete your profile
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        </template>

        {{-- Loading skeleton --}}
        <template x-if="loading">
            <div class="flex items-center gap-5 animate-pulse">
                <div class="h-[132px] w-[132px] rounded-full bg-gray-100 shrink-0"></div>
                <div class="flex-1 space-y-3">
                    <div class="h-3 w-2/3 rounded bg-gray-100"></div>
                    <div class="h-3 w-1/2 rounded bg-gray-100"></div>
                    <div class="h-3 w-3/5 rounded bg-gray-100"></div>
                </div>
            </div>
        </template>

        {{-- Failure --}}
        <template x-if="failed && !loading">
            <div class="flex items-center gap-4">
                <x-match-ring :score="null" size="lg" />
                <div>
                    <p class="text-sm text-gray-600">We couldn't score your match right now.</p>
                    <button type="button" @click="load()" class="mt-1 text-sm font-semibold text-brand-600 hover:text-brand-700">Try again</button>
                </div>
            </div>
        </template>

        {{-- Result --}}
        <template x-if="match && !loading">
            <div class="grid sm:grid-cols-[auto,1fr] gap-6 sm:gap-7 items-start">
                {{-- Live ring (mirrors x-match-ring geometry; driven by fetched score) --}}
                <div class="flex sm:flex-col items-center gap-3 sm:gap-2">
                    <span class="relative inline-flex items-center justify-center shrink-0" style="width:132px;height:132px"
                        role="img" :aria-label="`Match score ${match.score ?? match.percentage} out of 100`">
                        <svg class="absolute inset-0 -rotate-90" width="132" height="132" viewBox="0 0 132 132" fill="none" aria-hidden="true">
                            <circle cx="66" cy="66" r="57" :class="ringTrack()" stroke="currentColor" stroke-width="9" />
                            <circle cx="66" cy="66" r="57" :class="ringStroke()" stroke="currentColor" stroke-width="9" stroke-linecap="round"
                                :stroke-dasharray="2 * Math.PI * 57" :stroke-dashoffset="dashoffset()"
                                class="transition-[stroke-dashoffset] duration-700 ease-out" />
                        </svg>
                        <span class="relative flex flex-col items-center leading-none" :class="ringStroke()">
                            <span class="text-4xl font-extrabold" x-text="(match.score ?? match.percentage)"></span>
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 mt-0.5">match</span>
                        </span>
                    </span>
                </div>

                <div class="min-w-0">
                    <p class="text-gray-700" x-text="match.summary"></p>

                    {{-- Deterministic breakdown: the facts behind the score.
                         Built from a plain skills/requirements comparison — not
                         the model — so the chips can never contradict the number. --}}
                    <template x-if="match.breakdown && (match.breakdown.matchedSkills.length || match.breakdown.unmatchedSkills.length)">
                        <div class="mt-5">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                                Skills
                                <span class="font-semibold normal-case tracking-normal text-gray-400"
                                    x-text="`· ${match.breakdown.matchedSkills.length} of ${match.breakdown.matchedSkills.length + match.breakdown.unmatchedSkills.length} on your profile appear in this role`"></span>
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(skill, i) in match.breakdown.matchedSkills" :key="'ms'+i">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 text-green-700 ring-1 ring-green-200/70 px-2.5 py-1 text-xs font-semibold">
                                        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        <span x-text="skill"></span>
                                    </span>
                                </template>
                                <template x-for="(skill, i) in match.breakdown.unmatchedSkills" :key="'us'+i">
                                    <span class="inline-flex items-center rounded-full bg-gray-50 text-gray-400 ring-1 ring-gray-200 px-2.5 py-1 text-xs font-medium" x-text="skill"></span>
                                </template>
                            </div>
                            <p class="mt-2 text-2xs text-gray-400" x-show="match.breakdown.unmatchedSkills.length" x-cloak>
                                Muted skills are on your profile but aren't mentioned in this posting.
                            </p>
                        </div>
                    </template>

                    {{-- Experience delta, in plain language. --}}
                    <template x-if="match.breakdown && match.breakdown.experience.status !== 'none'">
                        <div class="mt-4 flex items-start gap-2 rounded-xl px-3 py-2.5 text-sm"
                            :class="match.breakdown.experience.status === 'met'
                                ? 'bg-green-50 text-green-800'
                                : (match.breakdown.experience.status === 'unmet' ? 'bg-accent-50 text-accent-700' : 'bg-gray-50 text-gray-600')">
                            {{-- met: check · unmet/unknown: clock --}}
                            <template x-if="match.breakdown.experience.status === 'met'">
                                <svg class="h-4 w-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </template>
                            <template x-if="match.breakdown.experience.status !== 'met'">
                                <svg class="h-4 w-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                            </template>
                            <span class="font-medium" x-text="match.breakdown.experience.label"></span>
                        </div>
                    </template>

                    <template x-if="match.strengths && match.strengths.length">
                        <div class="mt-5">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Why you fit</p>
                            <ul class="space-y-1.5">
                                <template x-for="(item, i) in match.strengths" :key="'s'+i">
                                    <li class="flex items-start gap-2 text-sm text-gray-700">
                                        <svg class="h-4 w-4 text-green-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                        <span x-text="item"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="match.gaps && match.gaps.length">
                        <div class="mt-5">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Close the gap</p>
                            <ul class="space-y-2.5">
                                <template x-for="(item, i) in match.gaps" :key="'g'+i">
                                    <li class="flex items-start gap-2 text-sm">
                                        <svg class="h-4 w-4 text-accent-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                        <span class="min-w-0">
                                            <span class="text-gray-700 font-medium" x-text="typeof item === 'string' ? item : item.gap"></span>
                                            <template x-if="typeof item === 'object' && item.suggestion">
                                                <span class="block text-gray-500 mt-0.5" x-text="item.suggestion"></span>
                                            </template>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <p class="mt-5 text-2xs text-gray-400">AI-generated estimate — a guide, not a decision.</p>
                </div>
            </div>
        </template>
    </div>
</section>
