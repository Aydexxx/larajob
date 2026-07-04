<x-app-layout>
    <x-slot name="title">For You</x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Header --}}
            <header class="mb-8">
                <span class="eyebrow">For you</span>
                <div class="mt-2 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
                            @if ($state === 'ranked')
                                Roles matched to you
                            @else
                                Welcome back{{ auth()->user()->name ? ', '.\Illuminate\Support\Str::before(auth()->user()->name, ' ') : '' }}
                            @endif
                        </h1>
                        <p class="text-sm text-gray-500 mt-1">
                            @switch($state)
                                @case('ranked')
                                    Ranked by how closely each role fits your profile — strongest matches first.
                                    @break
                                @case('unscorable')
                                    Add a little more to your profile and this becomes a feed built around you.
                                    @break
                                @case('pending')
                                    We're reading your profile to line up your best-fit roles.
                                    @break
                                @default
                                    We'll surface roles here as they match your profile.
                            @endswitch
                        </p>
                    </div>
                    <a href="{{ route('jobs.index') }}"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700 shrink-0">
                        Browse all jobs
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </header>

            @switch($state)

                {{-- ============ RANKED: personalized feed ============ --}}
                @case('ranked')
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($jobs as $job)
                            <x-job-card :job="$job" layout="grid"
                                :match-score="$job->match_score"
                                :match-endpoint="route('candidate.jobs.match', $job)"
                                data-reveal style="--reveal-delay: {{ min($loop->index, 9) * 40 }}ms" />
                        @endforeach
                    </div>
                    @break

                {{-- ============ EMPTY: ranked but nothing to match ============ --}}
                @case('empty')
                    <x-ui.card>
                        <x-ui.empty-state
                            context="matches"
                            title="No matching roles just yet"
                            description="There are no open roles that line up with your profile right now. Check back soon, or explore everything on the board.">
                            <x-slot name="action">
                                <x-ui.button :href="route('jobs.index')">Browse all jobs</x-ui.button>
                            </x-slot>
                        </x-ui.empty-state>
                    </x-ui.card>
                    @break

                {{-- ============ UNSCORABLE: complete your profile ============ --}}
                @case('unscorable')
                    <x-candidate.feed-prompt
                        icon="profile"
                        title="Complete your profile to personalize this feed"
                        description="Add your headline, a short bio and your skills. We'll use them to score every role and rank your best matches right here."
                        :cta-label="'Complete your profile'"
                        :cta-url="route('candidate.profile.edit')" />

                    @if ($jobs->isNotEmpty())
                        <div class="mt-10">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-4">Latest opportunities</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($jobs as $job)
                                    <x-job-card :job="$job" layout="grid" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @break

                {{-- ============ PENDING: analysis in progress ============ --}}
                @case('pending')
                    <x-candidate.feed-prompt
                        icon="spinner"
                        title="We're analyzing your profile"
                        description="Your best-fit roles will appear here within a few moments. Refresh in a little while to see your personalized matches.">
                        <x-slot name="action">
                            <x-ui.button :href="route('home')" variant="outline">Refresh feed</x-ui.button>
                        </x-slot>
                    </x-candidate.feed-prompt>

                    @if ($jobs->isNotEmpty())
                        <div class="mt-10">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-4">Latest opportunities</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($jobs as $job)
                                    <x-job-card :job="$job" layout="grid" />
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @break

            @endswitch

        </div>
    </div>
</x-app-layout>
