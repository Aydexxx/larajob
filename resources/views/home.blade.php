<x-app-layout>
    <!-- Hero -->
    <section class="bg-indigo-700 text-white py-20 px-4">
        <div class="max-w-3xl mx-auto text-center">
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                Find Your Next Job
            </h1>
            <p class="text-indigo-200 text-lg mb-10">
                Browse thousands of opportunities from top companies worldwide.
            </p>

            <form action="{{ route('jobs.index') }}" method="GET"
                class="flex flex-col sm:flex-row gap-3 bg-white rounded-xl p-2 shadow-lg max-w-2xl mx-auto">
                <input
                    type="text"
                    name="search"
                    placeholder="Job title or keyword"
                    value="{{ request('search') }}"
                    class="flex-1 px-4 py-2.5 text-gray-900 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
                <input
                    type="text"
                    name="location"
                    placeholder="Location"
                    value="{{ request('location') }}"
                    class="flex-1 px-4 py-2.5 text-gray-900 rounded-lg border-0 focus:ring-2 focus:ring-indigo-500 outline-none text-sm" />
                <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 shrink-0 text-sm">
                    Search Jobs
                </button>
            </form>
        </div>
    </section>

    <!-- Stats -->
    <section class="bg-white border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-center gap-8 sm:gap-16 text-center">
            <div>
                <p class="text-3xl font-bold text-indigo-600">{{ number_format($totalJobs) }}</p>
                <p class="text-sm text-gray-500 mt-1">Active Job Listings</p>
            </div>
            <div class="hidden sm:block w-px h-10 bg-gray-200"></div>
            <div>
                <p class="text-3xl font-bold text-indigo-600">{{ number_format($totalCompanies) }}</p>
                <p class="text-sm text-gray-500 mt-1">Companies Hiring</p>
            </div>
        </div>
    </section>

    <!-- Featured Jobs -->
    <section class="py-14 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Latest Opportunities</h2>
                <a href="{{ route('jobs.index') }}"
                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    View all jobs →
                </a>
            </div>

            @if ($featured->isEmpty())
                <p class="text-gray-500 text-sm">No active job listings yet.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($featured as $job)
                        <a href="{{ route('jobs.show', $job) }}"
                            class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md hover:border-indigo-200 transition-all block">

                            <!-- Company row -->
                            <div class="flex items-center gap-3 mb-4">
                                @if ($job->company?->logo)
                                    <img src="{{ Storage::url($job->company->logo) }}"
                                        alt="{{ $job->company->name }}"
                                        class="h-10 w-10 rounded-lg object-cover border border-gray-100 shrink-0" />
                                @else
                                    <div class="h-10 w-10 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                                        <span class="text-indigo-600 font-bold text-sm">
                                            {{ mb_strtoupper(mb_substr($job->company?->name ?? '?', 0, 1)) }}
                                        </span>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500 truncate">{{ $job->company?->name }}</p>
                                </div>
                            </div>

                            <!-- Title -->
                            <h3 class="font-semibold text-gray-900 mb-2 leading-snug">{{ $job->title }}</h3>

                            <!-- Meta -->
                            <div class="flex flex-wrap gap-2 mb-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-indigo-50 text-indigo-700">
                                    {{ str_replace('-', ' ', ucfirst($job->type)) }}
                                </span>
                                @if ($job->is_remote)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700">
                                        Remote
                                    </span>
                                @endif
                                @if ($job->location)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600">
                                        {{ $job->location }}
                                    </span>
                                @endif
                            </div>

                            <!-- Salary + date -->
                            <div class="flex items-center justify-between text-xs text-gray-400 mt-auto pt-2 border-t border-gray-50">
                                @if ($job->salary_min || $job->salary_max)
                                    <span>
                                        @if ($job->salary_min && $job->salary_max)
                                            ${{ number_format($job->salary_min / 1000, 0) }}k – ${{ number_format($job->salary_max / 1000, 0) }}k
                                        @elseif ($job->salary_min)
                                            From ${{ number_format($job->salary_min / 1000, 0) }}k
                                        @else
                                            Up to ${{ number_format($job->salary_max / 1000, 0) }}k
                                        @endif
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                <span>{{ $job->created_at->diffForHumans() }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-10 text-center">
                <a href="{{ route('jobs.index') }}"
                    class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700">
                    Browse All Jobs
                </a>
            </div>
        </div>
    </section>
</x-app-layout>
