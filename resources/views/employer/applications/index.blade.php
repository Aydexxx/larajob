<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Applications') }}
            </h2>
            <span class="text-sm text-gray-500">{{ $applications->total() }} total</span>
        </div>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filters -->
            <form method="GET" action="{{ route('employer.applications.index') }}"
                class="bg-white border border-gray-200 rounded-xl p-4 mb-6 flex flex-col sm:flex-row gap-3">

                <select name="job_id"
                    class="flex-1 border-gray-300 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500">
                    <option value="">All jobs</option>
                    @foreach ($jobs as $job)
                        <option value="{{ $job->id }}" {{ request('job_id') == $job->id ? 'selected' : '' }}>
                            {{ $job->title }}
                        </option>
                    @endforeach
                </select>

                <select name="status"
                    class="flex-1 sm:max-w-[180px] border-gray-300 rounded-lg text-sm focus:ring-brand-500 focus:border-brand-500">
                    <option value="">All statuses</option>
                    @foreach (['pending' => 'Pending', 'reviewed' => 'Reviewed', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <button type="submit"
                    class="px-5 py-2 bg-brand-600 text-white rounded-lg text-sm font-medium hover:bg-brand-700 shrink-0">
                    Filter
                </button>

                @if (request()->hasAny(['job_id', 'status']))
                    <a href="{{ route('employer.applications.index') }}"
                        class="px-5 py-2 border border-gray-300 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 shrink-0 text-center">
                        Clear
                    </a>
                @endif
            </form>

            @php
                $statusColors = [
                    'pending'  => 'bg-yellow-100 text-yellow-800',
                    'reviewed' => 'bg-blue-100 text-blue-800',
                    'accepted' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                ];
                $matchColors = [
                    'high'   => 'bg-green-100 text-green-700',
                    'medium' => 'bg-yellow-100 text-yellow-800',
                    'low'    => 'bg-gray-100 text-gray-600',
                ];
            @endphp

            {{-- Sort toggle — only when AI match scoring is available --}}
            @if ($aiEnabled)
                <div class="flex items-center justify-end gap-2 mb-3 text-sm">
                    <span class="text-gray-500">Sort:</span>
                    <a href="{{ route('employer.applications.index', array_merge(request()->except(['sort', 'page']))) }}"
                        class="px-3 py-1 rounded-full border {{ $sortByMatch ? 'border-gray-200 text-gray-500 hover:bg-gray-50' : 'border-brand-300 bg-brand-50 text-brand-700 font-medium' }}">
                        Most recent
                    </a>
                    <a href="{{ route('employer.applications.index', array_merge(request()->except('page'), ['sort' => 'match'])) }}"
                        class="px-3 py-1 rounded-full border {{ $sortByMatch ? 'border-brand-300 bg-brand-50 text-brand-700 font-medium' : 'border-gray-200 text-gray-500 hover:bg-gray-50' }}">
                        Best match
                    </a>
                </div>
            @endif

            <!-- Table -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                @forelse ($applications as $application)
                    <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-100 last:border-0 hover:bg-gray-50">

                        <!-- Candidate -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-medium text-gray-900 text-sm">
                                    {{ $application->user->name }}
                                </p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ ucfirst($application->status) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">
                                {{ $application->job?->title ?? '—' }}
                                @if ($application->user->candidateProfile?->location)
                                    · {{ $application->user->candidateProfile->location }}
                                @endif
                            </p>
                        </div>

                        <!-- Match score -->
                        @if ($aiEnabled)
                            <div class="shrink-0 w-20 text-center hidden sm:block">
                                @if ($application->match_percentage !== null)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                        {{ $matchColors[$application->match_result?->tier()] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $application->match_percentage }}% match
                                    </span>
                                @else
                                    <span class="text-xs text-gray-300" title="Score not computed yet">—</span>
                                @endif
                            </div>
                        @endif

                        <!-- Date -->
                        <p class="text-xs text-gray-400 shrink-0 hidden sm:block">
                            {{ $application->created_at->diffForHumans() }}
                        </p>

                        <!-- Action -->
                        <a href="{{ route('employer.applications.show', $application) }}"
                            class="shrink-0 text-xs px-3 py-1.5 border border-brand-300 text-brand-600 rounded-md font-medium hover:bg-brand-50">
                            View
                        </a>
                    </div>
                @empty
                    @if (request()->hasAny(['job_id', 'status']))
                        <x-ui.empty-state
                            context="search"
                            title="No applications match these filters"
                            description="Try a different job or status, or clear the filters to see everything.">
                            <x-slot name="action">
                                <x-ui.button :href="route('employer.applications.index')" variant="outline">Clear filters</x-ui.button>
                            </x-slot>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state
                            context="applications"
                            title="No applications yet"
                            description="Applications will show up here as soon as candidates start applying to your jobs." />
                    @endif
                @endforelse
            </div>

            @if ($applications->hasPages())
                <div class="mt-6">
                    {{ $applications->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
