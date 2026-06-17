<x-app-layout>
    <x-slot name="title">Browse Jobs</x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-6">
                <h1 class="text-2xl font-extrabold text-gray-900">Browse jobs</h1>
                <p class="text-sm text-gray-500 mt-1">Find your next role from {{ number_format($jobs->total()) }} open {{ Str::plural('position', $jobs->total()) }}.</p>
            </div>

            <!-- Search bar -->
            <form method="GET" action="{{ route('jobs.index') }}" id="filter-form" data-loading-form>
                @if (request('company'))
                    <input type="hidden" name="company" value="{{ request('company') }}">
                @endif

                <div class="surface flex flex-col sm:flex-row gap-2 p-2 mb-2">
                    <div class="flex-1 flex items-center gap-2 px-3">
                        <svg class="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Job title or keyword"
                            class="w-full border-0 py-2.5 text-gray-900 placeholder-gray-400 focus:ring-0 text-sm bg-transparent" />
                    </div>
                    <div class="hidden sm:block w-px bg-gray-200 my-2"></div>
                    <div class="flex-1 flex items-center gap-2 px-3">
                        <svg class="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                        <input type="text" name="location" value="{{ request('location') }}" placeholder="Location"
                            class="w-full border-0 py-2.5 text-gray-900 placeholder-gray-400 focus:ring-0 text-sm bg-transparent" />
                    </div>
                    <x-ui.button type="submit">Search</x-ui.button>
                </div>

                <div class="flex items-center gap-1.5 mb-6 min-h-[1.25rem]">
                    @if ($isSemanticSearch)
                        <svg class="h-3.5 w-3.5 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z"/></svg>
                        <x-ui.badge color="brand">Smart search</x-ui.badge>
                        <span class="text-xs text-gray-400">Results ranked by meaning, not just keywords.</span>
                    @endif
                </div>

                <!-- Active filter chip -->
                @if (request('company'))
                    <div class="flex items-center gap-2 mb-6">
                        <span class="text-sm text-gray-500">Showing roles at</span>
                        <x-ui.badge color="brand" size="md">{{ request('company') }}</x-ui.badge>
                        <a href="{{ route('jobs.index') }}" class="text-sm text-gray-400 hover:text-gray-700">Clear</a>
                    </div>
                @endif

                <div class="flex flex-col lg:flex-row gap-8">

                    <!-- Sidebar filters -->
                    <aside class="lg:w-64 shrink-0" x-data="{ open: false }">
                        <!-- Mobile toggle -->
                        <button type="button" @click="open = !open"
                            class="lg:hidden w-full flex items-center justify-between surface px-4 py-3 text-sm font-medium text-gray-700 mb-3">
                            <span>Filters</span>
                            <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div :class="{ 'hidden': !open }" class="hidden lg:block space-y-4">
                            <!-- Job type -->
                            <div class="surface p-5">
                                <h3 class="text-sm font-semibold text-gray-900 mb-3">Job type</h3>
                                <div class="space-y-2.5">
                                    @foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $value => $label)
                                        <label class="flex items-center gap-2.5 cursor-pointer group">
                                            <input type="checkbox" name="types[]" value="{{ $value }}"
                                                {{ in_array($value, (array) request('types', [])) ? 'checked' : '' }}
                                                onchange="document.getElementById('filter-form').requestSubmit()"
                                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                            <span class="text-sm text-gray-600 group-hover:text-gray-900">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Remote -->
                            <div class="surface p-5">
                                <h3 class="text-sm font-semibold text-gray-900 mb-3">Work mode</h3>
                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                    <input type="checkbox" name="remote" value="1"
                                        {{ request()->boolean('remote') ? 'checked' : '' }}
                                        onchange="document.getElementById('filter-form').requestSubmit()"
                                        class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                                    <span class="text-sm text-gray-600 group-hover:text-gray-900">Remote only</span>
                                </label>
                            </div>

                            <!-- Minimum salary -->
                            <div class="surface p-5">
                                <h3 class="text-sm font-semibold text-gray-900 mb-3">Minimum salary</h3>
                                <input type="number" name="salary_min" min="0" step="5000"
                                    value="{{ request('salary_min') }}"
                                    placeholder="e.g. 50000"
                                    class="w-full rounded-xl border-gray-300 text-sm focus:ring-brand-500 focus:border-brand-500" />
                                <x-ui.button type="submit" variant="secondary" size="sm" class="mt-3 w-full">Apply</x-ui.button>
                            </div>

                            @if (request()->hasAny(['search', 'location', 'types', 'remote', 'salary_min', 'company']))
                                <a href="{{ route('jobs.index') }}"
                                    class="block text-center text-sm text-gray-500 hover:text-gray-800 py-2">
                                    Clear all filters
                                </a>
                            @endif
                        </div>
                    </aside>

                    <!-- Job list -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 mb-4">
                            {{ number_format($jobs->total()) }} {{ Str::plural('job', $jobs->total()) }} found
                        </p>

                        <div class="space-y-4">
                            @forelse ($jobs as $job)
                                <x-job-card :job="$job" layout="row" data-reveal style="--reveal-delay: {{ min($loop->index, 8) * 45 }}ms" />
                            @empty
                                <x-ui.card>
                                    <x-ui.empty-state
                                        title="No jobs match your search"
                                        description="Try adjusting your filters or search terms to see more results.">
                                        <x-slot name="action">
                                            <x-ui.button :href="route('jobs.index')" variant="outline" size="sm">Clear filters</x-ui.button>
                                        </x-slot>
                                    </x-ui.empty-state>
                                </x-ui.card>
                            @endforelse
                        </div>

                        @if ($jobs->hasPages())
                            <div class="mt-8">
                                {{ $jobs->links() }}
                            </div>
                        @endif
                    </div>

                </div>
            </form>

        </div>
    </div>
</x-app-layout>
