<x-app-layout>
    <x-slot name="title">Find your next role</x-slot>

    <!-- ============ HERO ============ -->
    <section class="relative overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 text-white">
        <!-- decorative dotted grid -->
        <div class="absolute inset-0 opacity-[0.18]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 32px 32px;" aria-hidden="true"></div>
        <!-- glow blobs -->
        <div class="absolute -top-24 -right-24 h-72 w-72 rounded-full bg-brand-400/30 blur-3xl motion-safe:animate-float-slow" aria-hidden="true"></div>
        <div class="absolute -bottom-32 -left-20 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl motion-safe:animate-float-slow [animation-delay:-4.5s]" aria-hidden="true"></div>

        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 text-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 ring-1 ring-white/20 px-3.5 py-1.5 text-sm font-medium text-brand-50 animate-fade-in-up">
                <span class="relative flex h-1.5 w-1.5">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 motion-safe:animate-ping"></span>
                    <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-green-400"></span>
                </span>
                {{ number_format($totalJobs) }} open roles hiring right now
            </span>

            <h1 class="mt-6 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.05] animate-fade-in-up">
                Find a job that<br class="hidden sm:block">
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-white to-brand-200">moves you forward.</span>
            </h1>

            <p class="mt-5 text-lg text-brand-100 max-w-2xl mx-auto animate-fade-in-up">
                Browse thousands of opportunities from top companies worldwide — across engineering, design, product, and more.
            </p>

            <!-- Search -->
            <form action="{{ route('jobs.index') }}" method="GET" data-loading-form
                class="mt-10 max-w-3xl mx-auto bg-white rounded-2xl p-2 shadow-elevated flex flex-col sm:flex-row gap-2 animate-fade-in-up">
                <div class="flex-1 flex items-center gap-2 px-3">
                    <svg class="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Job title or keyword"
                        class="w-full border-0 py-3 text-gray-900 placeholder-gray-400 focus:ring-0 text-sm bg-transparent" />
                </div>
                <div class="hidden sm:block w-px bg-gray-200 my-2"></div>
                <div class="flex-1 flex items-center gap-2 px-3">
                    <svg class="h-5 w-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    <input type="text" name="location" value="{{ request('location') }}" placeholder="Location"
                        class="w-full border-0 py-3 text-gray-900 placeholder-gray-400 focus:ring-0 text-sm bg-transparent" />
                </div>
                <x-ui.button type="submit" size="lg" class="sm:w-auto justify-center">
                    Search Jobs
                </x-ui.button>
            </form>

            <!-- Popular searches -->
            <div class="mt-6 flex flex-wrap items-center justify-center gap-2 text-sm animate-fade-in-up">
                <span class="text-brand-200">Popular:</span>
                @foreach (['Software Engineer', 'Product Designer', 'Data Scientist', 'Marketing'] as $term)
                    <a href="{{ route('jobs.index', ['search' => $term]) }}"
                        class="rounded-full bg-white/10 hover:bg-white/20 ring-1 ring-white/15 px-3 py-1 transition-all duration-200 motion-safe:hover:scale-105 motion-safe:hover:-translate-y-0.5">
                        {{ $term }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ============ STATS ============ -->
    <section class="bg-white border-b border-gray-200">
        <div data-reveal class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid grid-cols-3 gap-6 text-center">
            <div>
                <p class="text-3xl sm:text-4xl font-extrabold text-brand-600" data-count-to="{{ $totalJobs }}">{{ number_format($totalJobs) }}</p>
                <p class="text-sm text-gray-500 mt-1">Active jobs</p>
            </div>
            <div class="border-x border-gray-100">
                <p class="text-3xl sm:text-4xl font-extrabold text-brand-600" data-count-to="{{ $totalCompanies }}">{{ number_format($totalCompanies) }}</p>
                <p class="text-sm text-gray-500 mt-1">Companies hiring</p>
            </div>
            <div>
                <p class="text-3xl sm:text-4xl font-extrabold text-brand-600" data-count-to="{{ $totalCandidates }}">{{ number_format($totalCandidates) }}</p>
                <p class="text-sm text-gray-500 mt-1">Candidates</p>
            </div>
        </div>
    </section>

    <!-- ============ HOW IT WORKS ============ -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div data-reveal class="text-center max-w-2xl mx-auto">
                <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">How it works</p>
                <h2 class="mt-2 text-3xl font-extrabold text-gray-900">Built for both sides of the table</h2>
                <p class="mt-3 text-gray-500">Whether you're looking for your next role or your next hire, getting started takes minutes.</p>
            </div>

            <div class="mt-14 grid lg:grid-cols-2 gap-6">
                <!-- Candidates -->
                <div data-reveal class="surface p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0"/></svg>
                        </span>
                        <h3 class="text-lg font-bold text-gray-900">For candidates</h3>
                    </div>
                    <ol class="space-y-5">
                        @foreach ([
                            ['Create your profile', 'Add your headline, skills and experience so employers can find you.'],
                            ['Search & apply', 'Filter by role, salary and location, then apply in a couple of clicks.'],
                            ['Get hired', 'Track every application status and hear back directly from employers.'],
                        ] as $i => $step)
                            <li class="flex gap-4">
                                <span class="shrink-0 h-8 w-8 rounded-full bg-brand-600 text-white text-sm font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $step[0] }}</p>
                                    <p class="text-sm text-gray-500 mt-0.5">{{ $step[1] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-8">
                        <x-ui.button :href="route('register')" variant="primary">Find jobs</x-ui.button>
                    </div>
                </div>

                <!-- Employers -->
                <div data-reveal style="--reveal-delay: 120ms" class="surface p-8">
                    <div class="flex items-center gap-3 mb-6">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-accent-100 text-accent-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"/></svg>
                        </span>
                        <h3 class="text-lg font-bold text-gray-900">For employers</h3>
                    </div>
                    <ol class="space-y-5">
                        @foreach ([
                            ['Set up your company', 'Create a company profile that showcases your brand to candidates.'],
                            ['Post a job', 'Publish roles with rich descriptions, salary ranges and requirements.'],
                            ['Hire with confidence', 'Review applicants, update statuses, and notify candidates automatically.'],
                        ] as $i => $step)
                            <li class="flex gap-4">
                                <span class="shrink-0 h-8 w-8 rounded-full bg-gray-900 text-white text-sm font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $step[0] }}</p>
                                    <p class="text-sm text-gray-500 mt-0.5">{{ $step[1] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-8">
                        <x-ui.button :href="route('register')" variant="outline">Post a job</x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ FEATURED JOBS ============ -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div data-reveal class="flex items-end justify-between mb-10">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Fresh openings</p>
                    <h2 class="mt-2 text-3xl font-extrabold text-gray-900">Latest opportunities</h2>
                </div>
                <a href="{{ route('jobs.index') }}" class="hidden sm:inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">
                    View all jobs
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>

            @if ($featured->isEmpty())
                <x-ui.empty-state title="No openings yet" description="New roles are posted every day — check back soon." />
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    @foreach ($featured as $job)
                        <x-job-card :job="$job" layout="grid" data-reveal style="--reveal-delay: {{ $loop->index * 70 }}ms" />
                    @endforeach
                </div>
            @endif

            <div class="mt-10 text-center sm:hidden">
                <x-ui.button :href="route('jobs.index')" variant="outline">View all jobs</x-ui.button>
            </div>
        </div>
    </section>

    <!-- ============ FEATURED COMPANIES ============ -->
    @if ($companies->isNotEmpty())
        <section class="py-20 bg-gray-50 border-y border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div data-reveal class="text-center max-w-2xl mx-auto mb-12">
                    <p class="text-sm font-semibold uppercase tracking-widest text-brand-600">Hiring now</p>
                    <h2 class="mt-2 text-3xl font-extrabold text-gray-900">Companies on LaraJob</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    @foreach ($companies as $company)
                        <a href="{{ route('jobs.index', ['company' => $company->name]) }}"
                            data-reveal style="--reveal-delay: {{ $loop->index * 60 }}ms"
                            class="surface group p-6 text-center transition-all duration-200 hover:shadow-elevated hover:border-brand-200 motion-safe:hover:-translate-y-0.5">
                            <div class="flex justify-center mb-4">
                                <x-ui.avatar :name="$company->name" :src="$company->logo ? Storage::url($company->logo) : null" size="lg" />
                            </div>
                            <h3 class="font-semibold text-gray-900 truncate group-hover:text-brand-700 transition-colors">
                                {{ $company->name }}
                            </h3>
                            @if ($company->location)
                                <p class="text-xs text-gray-500 mt-1 truncate">{{ $company->location }}</p>
                            @endif
                            <p class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-600">
                                {{ $company->active_jobs_count }} open {{ Str::plural('role', $company->active_jobs_count) }}
                            </p>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <!-- ============ CTA ============ -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div data-reveal class="relative overflow-hidden rounded-4xl bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 px-6 py-16 sm:px-16 text-center shadow-glow">
                <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 28px 28px;" aria-hidden="true"></div>
                <div class="relative max-w-2xl mx-auto">
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-white">Ready to make your next move?</h2>
                    <p class="mt-4 text-lg text-brand-100">
                        Create a free account and start applying — or post your first job in minutes.
                    </p>
                    <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                        <x-ui.button :href="route('register')" variant="white" size="lg">Create free account</x-ui.button>
                        <x-ui.button :href="route('jobs.index')" variant="ghost-light" size="lg">
                            Browse jobs
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
