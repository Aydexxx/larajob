<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Ranked applicants') }} — {{ $job->title }}
            </h2>
            <a href="{{ route('employer.jobs.index') }}"
                class="text-sm text-brand-600 hover:text-brand-800 underline">
                {{ __('Back to jobs') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <p class="text-sm text-gray-500">
                Ranked by match score against this listing.
                @if ($aiEnabled)
                    Expand a candidate for an AI summary of why they fit.
                @else
                    Turn on the AI provider for a written summary of each candidate.
                @endif
            </p>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                @forelse ($ranked as $index => $application)
                    <div class="border-b border-gray-100 last:border-0"
                        x-data="{
                            open: false,
                            loaded: false,
                            loading: false,
                            failed: false,
                            incomplete: false,
                            summary: null,
                            toggle() {
                                this.open = !this.open;
                                if (this.open && ! this.loaded && {{ $aiEnabled ? 'true' : 'false' }}) this.load();
                            },
                            load() {
                                this.loading = true;
                                this.failed = false;
                                fetch('{{ route('employer.jobs.applicant-summary', [$job, $application]) }}', { headers: { Accept: 'application/json' } })
                                    .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                                    .then((data) => {
                                        if (data.status === 'incomplete_profile') this.incomplete = true;
                                        else if (data.status === 'ok' && data.summary) this.summary = data.summary;
                                        else this.failed = true;
                                    })
                                    .catch(() => { this.failed = true; })
                                    .finally(() => { this.loading = false; this.loaded = true; });
                            },
                        }">
                        <div class="flex items-center gap-4 p-4">
                            <div class="w-8 text-center text-lg font-bold text-gray-400 shrink-0">
                                {{ $index + 1 }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 truncate">{{ $application->user?->name ?? __('Unknown candidate') }}</p>
                                <p class="text-sm text-gray-500 truncate">
                                    {{ $application->user?->candidateProfile?->headline ?? __('No headline') }}
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                @if (! is_null($application->match_score))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-brand-50 text-brand-700 text-sm font-semibold">
                                        {{ $application->match_score }}% match
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">Not scorable</span>
                                @endif
                            </div>

                            <div class="shrink-0 flex items-center gap-2">
                                @if ($aiEnabled)
                                    <button type="button" @click="toggle()"
                                        class="text-xs px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 font-medium hover:bg-gray-50">
                                        <span x-show="!open">{{ __('Why?') }}</span>
                                        <span x-show="open" x-cloak>{{ __('Hide') }}</span>
                                    </button>
                                @endif
                                <a href="{{ route('employer.applications.show', $application) }}"
                                    class="text-xs px-3 py-1.5 rounded-md border border-brand-300 text-brand-600 font-medium hover:bg-brand-50">
                                    {{ __('View') }}
                                </a>
                            </div>
                        </div>

                        {{-- Lazily fetched AI summary --}}
                        @if ($aiEnabled)
                            <div x-show="open" x-cloak class="px-4 pb-4 pl-16">
                                <template x-if="loading">
                                    <div class="animate-pulse space-y-2">
                                        <div class="h-3 w-2/3 rounded bg-gray-100"></div>
                                        <div class="h-3 w-1/2 rounded bg-gray-100"></div>
                                    </div>
                                </template>
                                <template x-if="incomplete">
                                    <p class="text-sm text-gray-500">This candidate's profile isn't complete enough to summarize.</p>
                                </template>
                                <template x-if="failed && !loading">
                                    <div class="text-sm text-gray-600">
                                        Couldn't load a summary.
                                        <button type="button" @click="load()" class="font-semibold text-brand-600 hover:text-brand-700">Try again</button>
                                    </div>
                                </template>
                                <template x-if="summary && !loading">
                                    <div class="text-sm">
                                        <p class="text-gray-700" x-text="summary.sentence"></p>
                                        <template x-if="summary.reason">
                                            <p class="mt-1 text-gray-500"><span class="font-semibold text-gray-600">Why:</span> <span x-text="summary.reason"></span></p>
                                        </template>
                                        <p class="mt-1 text-2xs text-gray-400">AI-generated estimate — a guide, not a decision.</p>
                                    </div>
                                </template>
                            </div>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state
                        context="applicants"
                        title="No applicants yet"
                        description="Once candidates apply to this role, they'll be ranked here by how closely they match your listing.">
                        <x-slot name="action">
                            <x-ui.button :href="route('employer.jobs.index')" variant="outline">Back to jobs</x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
