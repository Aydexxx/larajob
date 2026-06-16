<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Browse Jobs') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Search bar -->
            <form method="GET" action="{{ route('jobs.index') }}" id="filter-form">
                <div class="flex flex-col sm:flex-row gap-3 mb-8 bg-white border border-gray-200 rounded-xl p-3 shadow-sm">
                    <input type="text" name="search" value="{{ request('search') }}"
                        placeholder="Job title or keyword"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" />
                    <input type="text" name="location" value="{{ request('location') }}"
                        placeholder="Location"
                        class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none" />
                    <button type="submit"
                        class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 text-sm shrink-0">
                        Search
                    </button>
                    @if (request()->hasAny(['search', 'location', 'types', 'remote', 'salary_min']))
                        <a href="{{ route('jobs.index') }}"
                            class="px-4 py-2.5 border border-gray-300 text-gray-600 font-medium rounded-lg hover:bg-gray-50 text-sm shrink-0 text-center">
                            Clear
                        </a>
                    @endif
                </div>

                <div class="flex flex-col lg:flex-row gap-8">

                    <!-- Sidebar filters -->
                    <aside class="lg:w-60 shrink-0" x-data="{ open: false }">

                        <!-- Mobile toggle -->
                        <button type="button" @click="open = !open"
                            class="lg:hidden w-full flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 mb-3">
                            <span>Filters</span>
                            <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div :class="{ 'hidden': !open }" class="hidden lg:block space-y-6">

                            <!-- Job type -->
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Job Type</h3>
                                <div class="space-y-2">
                                    @foreach (['full-time' => 'Full Time', 'part-time' => 'Part Time', 'contract' => 'Contract', 'internship' => 'Internship'] as $value => $label)
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="types[]" value="{{ $value }}"
                                                {{ in_array($value, (array) request('types', [])) ? 'checked' : '' }}
                                                onchange="document.getElementById('filter-form').submit()"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                            <span class="text-sm text-gray-600">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Remote -->
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Work Mode</h3>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="remote" value="1"
                                        {{ request()->boolean('remote') ? 'checked' : '' }}
                                        onchange="document.getElementById('filter-form').submit()"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-sm text-gray-600">Remote only</span>
                                </label>
                            </div>

                            <!-- Minimum salary -->
                            <div class="bg-white border border-gray-200 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3">Minimum Salary</h3>
                                <input type="number" name="salary_min" min="0" step="5000"
                                    value="{{ request('salary_min') }}"
                                    placeholder="e.g. 50000"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                <button type="submit"
                                    class="mt-2 w-full py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </aside>

                    <!-- Job list -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-500 mb-5">
                            {{ $jobs->total() }} {{ Str::plural('job', $jobs->total()) }} found
                        </p>

                        @forelse ($jobs as $job)
                            <a href="{{ route('jobs.show', $job) }}"
                                class="block bg-white border border-gray-200 rounded-xl p-5 mb-4 hover:shadow-md hover:border-indigo-200 transition-all">

                                <div class="flex items-start gap-4">
                                    <!-- Logo -->
                                    @if ($job->company?->logo)
                                        <img src="{{ Storage::url($job->company->logo) }}"
                                            alt="{{ $job->company->name }}"
                                            class="h-12 w-12 rounded-lg object-cover border border-gray-100 shrink-0" />
                                    @else
                                        <div class="h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                                            <span class="text-indigo-600 font-bold">
                                                {{ mb_strtoupper(mb_substr($job->company?->name ?? '?', 0, 1)) }}
                                            </span>
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                            <div>
                                                <h3 class="font-semibold text-gray-900 text-base leading-snug">
                                                    {{ $job->title }}
                                                </h3>
                                                <p class="text-sm text-gray-500 mt-0.5">
                                                    {{ $job->company?->name }}
                                                </p>
                                            </div>
                                            <span class="text-xs text-gray-400 shrink-0">
                                                {{ $job->created_at->diffForHumans() }}
                                            </span>
                                        </div>

                                        <div class="flex flex-wrap gap-2 mt-3">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700">
                                                {{ str_replace('-', ' ', ucfirst($job->type)) }}
                                            </span>
                                            @if ($job->is_remote)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700">
                                                    Remote
                                                </span>
                                            @endif
                                            @if ($job->location)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                                                    {{ $job->location }}
                                                </span>
                                            @endif
                                            @if ($job->salary_min || $job->salary_max)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-yellow-50 text-yellow-700">
                                                    @if ($job->salary_min && $job->salary_max)
                                                        ${{ number_format($job->salary_min / 1000, 0) }}k – ${{ number_format($job->salary_max / 1000, 0) }}k
                                                    @elseif ($job->salary_min)
                                                        From ${{ number_format($job->salary_min / 1000, 0) }}k
                                                    @else
                                                        Up to ${{ number_format($job->salary_max / 1000, 0) }}k
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="bg-white border border-gray-200 rounded-xl p-12 text-center">
                                <p class="text-gray-500 text-sm mb-2">No jobs match your search.</p>
                                <a href="{{ route('jobs.index') }}"
                                    class="text-indigo-600 text-sm hover:underline">Clear filters</a>
                            </div>
                        @endforelse

                        <!-- Pagination -->
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
