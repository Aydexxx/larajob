<x-app-layout>
    <x-slot name="title">{{ $job->title }}</x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Back link -->
            <a href="{{ route('jobs.index') }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-800 mb-6 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                Back to jobs
            </a>

            <div class="flex flex-col lg:flex-row gap-8">

                <!-- Main content -->
                <article class="flex-1 min-w-0 space-y-6">
                    {{-- Prominent AI match section — candidates only, hidden when AI is off --}}
                    @if ($matchEnabled)
                        <x-match-panel
                            :endpoint="route('candidate.jobs.match', $job)"
                            :result="$matchInitial"
                            :incomplete="$matchIncomplete"
                            :profile-url="route('candidate.profile.edit')" />
                    @endif

                    <x-ui.card padding="p-7 sm:p-8">
                        <!-- Header -->
                        <div class="flex items-start gap-4 mb-6">
                            <x-ui.avatar :name="$job->company?->name ?? '?'" :src="$job->company?->logo ? Storage::url($job->company->logo) : null" size="lg" />
                            <div class="min-w-0">
                                <h1 class="text-2xl font-extrabold text-gray-900 leading-tight">{{ $job->title }}</h1>
                                <p class="text-gray-500 mt-1">{{ $job->company?->name }}</p>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="flex flex-wrap gap-2 mb-8">
                            <x-ui.badge :status="$job->type" size="md" />
                            @if ($job->is_remote)
                                <x-ui.badge color="green" size="md" dot>Remote</x-ui.badge>
                            @endif
                            @if ($job->location)
                                <x-ui.badge color="gray" size="md">{{ $job->location }}</x-ui.badge>
                            @endif
                            @if ($job->salary_min || $job->salary_max)
                                <x-ui.badge color="accent" size="md">
                                    @if ($job->salary_min && $job->salary_max)
                                        ${{ number_format($job->salary_min) }} – ${{ number_format($job->salary_max) }}
                                    @elseif ($job->salary_min)
                                        From ${{ number_format($job->salary_min) }}
                                    @else
                                        Up to ${{ number_format($job->salary_max) }}
                                    @endif
                                </x-ui.badge>
                            @endif
                            @if ($job->expires_at)
                                <x-ui.badge color="gray" size="md">Closes {{ $job->expires_at->format('M j, Y') }}</x-ui.badge>
                            @endif
                        </div>

                        <!-- Description -->
                        <div class="mb-8">
                            <h2 class="text-base font-bold text-gray-900 mb-3">About the role</h2>
                            <div class="rich-text">{{ $job->description }}</div>
                        </div>

                        @if ($job->requirements)
                            <div>
                                <h2 class="text-base font-bold text-gray-900 mb-3">Requirements</h2>
                                <div class="rich-text">{{ $job->requirements }}</div>
                            </div>
                        @endif
                    </x-ui.card>
                </article>

                <!-- Sidebar -->
                <aside class="lg:w-80 shrink-0 space-y-5">
                    <!-- Apply card -->
                    <x-ui.card>
                        <h3 class="font-bold text-gray-900 mb-4">Apply for this role</h3>

                        @auth
                            @if (Auth::user()->role === 'candidate')
                                @if ($hasApplied)
                                    <x-ui.alert variant="success">You've already applied to this role.</x-ui.alert>
                                    <a href="{{ route('candidate.applications.index') }}"
                                        class="block w-full text-center text-sm font-medium text-brand-600 hover:text-brand-700 mt-3">
                                        View my applications
                                    </a>
                                @else
                                    <x-ui.button :href="route('candidate.applications.create', ['job_id' => $job->id])" size="lg" class="w-full">
                                        Apply now
                                    </x-ui.button>
                                @endif
                            @elseif (Auth::user()->role === 'employer')
                                <p class="text-sm text-gray-500">You're signed in as an employer.</p>
                            @endif
                        @else
                            <p class="text-sm text-gray-500 mb-4">You need a candidate account to apply.</p>
                            <div class="space-y-2">
                                <x-ui.button :href="route('register')" size="lg" class="w-full">Create account</x-ui.button>
                                <x-ui.button :href="route('login')" variant="outline" class="w-full">Log in</x-ui.button>
                            </div>
                        @endauth

                        <p class="text-xs text-gray-400 mt-4 text-center">
                            Posted {{ $job->created_at->diffForHumans() }}
                        </p>
                    </x-ui.card>

                    <!-- Company card -->
                    @if ($job->company)
                        <x-ui.card>
                            <h3 class="font-bold text-gray-900 mb-4">About the company</h3>

                            <div class="flex items-center gap-3 mb-4">
                                <x-ui.avatar :name="$job->company->name" :src="$job->company->logo ? Storage::url($job->company->logo) : null" size="sm" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 text-sm truncate">{{ $job->company->name }}</p>
                                    @if ($job->company->location)
                                        <p class="text-xs text-gray-500 truncate">{{ $job->company->location }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($job->company->description)
                                <p class="text-sm text-gray-600 mb-4 line-clamp-4">{{ $job->company->description }}</p>
                            @endif

                            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                <a href="{{ route('jobs.index', ['company' => $job->company->name]) }}"
                                    class="text-sm font-medium text-brand-600 hover:text-brand-700">
                                    See all roles
                                </a>
                                @if ($job->company->website)
                                    <a href="{{ $job->company->website }}" target="_blank" rel="noopener noreferrer"
                                        class="text-sm text-gray-500 hover:text-gray-800">
                                        Visit website &rarr;
                                    </a>
                                @endif
                            </div>
                        </x-ui.card>
                    @endif

                    {{-- Ask about this role: grounded chat over the listing + company profile. Always visible, unlike the AI-only cards below. --}}
                    <x-ask-about-job :job="$job" />

                    {{-- Similar jobs (semantic match) — hidden entirely when AI is disabled --}}
                    @if ($similarJobs->isNotEmpty())
                        <x-ui.card>
                            <div class="flex items-center gap-1.5 mb-4">
                                <h3 class="font-bold text-gray-900">Similar jobs</h3>
                                <x-ui.badge color="brand" size="sm">Smart match</x-ui.badge>
                            </div>

                            <div class="space-y-3">
                                @foreach ($similarJobs as $similarJob)
                                    <a href="{{ route('jobs.show', $similarJob) }}" class="block group">
                                        <p class="text-sm font-semibold text-gray-900 group-hover:text-brand-700 transition-colors truncate">
                                            {{ $similarJob->title }}
                                        </p>
                                        <p class="text-xs text-gray-500 truncate">{{ $similarJob->company?->name }} &middot; {{ $similarJob->location }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </x-ui.card>
                    @endif
                </aside>

            </div>
        </div>
    </div>
</x-app-layout>
