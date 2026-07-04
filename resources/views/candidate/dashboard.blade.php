<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-900 leading-tight">Candidate Dashboard</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @php
                $completion = $profile ? $profile->completionPercent() : 0;
                $completionColor = $completion >= 80 ? 'text-green-600' : ($completion >= 40 ? 'text-accent-500' : 'text-red-500');
            @endphp

            <!-- Welcome + quick actions -->
            <x-ui.card class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-bold text-gray-900 text-lg">Welcome back, {{ $user->name }} 👋</h3>
                    <p class="text-sm text-gray-500 mt-1">Find your next opportunity today.</p>
                </div>
                <x-ui.button :href="route('jobs.index')" class="shrink-0">Browse jobs</x-ui.button>
            </x-ui.card>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="surface p-5 text-center">
                    <p class="text-3xl font-extrabold text-brand-600">{{ $applicationCount }}</p>
                    <p class="text-sm text-gray-500 mt-1">Applications sent</p>
                    <a href="{{ route('candidate.applications.index') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">View all →</a>
                </div>

                <div class="surface p-5 text-center">
                    <p class="text-3xl font-extrabold {{ $completionColor }}">{{ $completion }}%</p>
                    <p class="text-sm text-gray-500 mt-1">Profile complete</p>
                    <a href="{{ route('candidate.profile.edit') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">
                        {{ $completion < 100 ? 'Complete profile →' : 'Edit profile →' }}
                    </a>
                </div>

                <div class="surface p-5 text-center">
                    @if ($profile?->isAnalyzingResume())
                        <svg class="mx-auto h-7 w-7 text-brand-500 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1C5.9 1 1 5.9 1 12h3z"></path>
                        </svg>
                        <p class="text-sm text-gray-500 mt-1">Analyzing resume…</p>
                        <a href="{{ route('candidate.profile.edit') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">View progress →</a>
                    @elseif ($profile?->resume_path)
                        <p class="text-3xl font-extrabold text-green-600">✓</p>
                        <p class="text-sm text-gray-500 mt-1">Resume uploaded</p>
                        <a href="{{ route('candidate.profile.resume') }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">View resume →</a>
                    @else
                        <p class="text-3xl font-extrabold text-gray-300">–</p>
                        <p class="text-sm text-gray-500 mt-1">No resume yet</p>
                        <a href="{{ route('candidate.profile.edit') }}" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">Upload resume →</a>
                    @endif
                </div>
            </div>

            <!-- Profile incomplete banner -->
            @if ($completion < 50)
                <x-ui.alert variant="warning" title="Your profile is incomplete">
                    Complete your profile to stand out to employers.
                    <a href="{{ route('candidate.profile.edit') }}" class="font-semibold underline">Complete now →</a>
                </x-ui.alert>
            @endif

            <!-- Recent applications -->
            @if ($recentApplications->isNotEmpty())
                <x-ui.card padding="p-0">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900">Recent applications</h3>
                        <a href="{{ route('candidate.applications.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">View all</a>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($recentApplications as $application)
                            <a href="{{ route('candidate.applications.show', $application) }}"
                                class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-gray-50 transition">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        {{ $application->job?->title ?? 'Job removed' }}
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">
                                        {{ $application->job?->company?->name }} · {{ $application->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <x-ui.badge :status="$application->status" class="shrink-0" />
                            </a>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            {{-- Secondary to the For You feed: a short, rule-based "learn these
                 next" list. Hidden entirely when there's nothing meaningful to
                 suggest — see SkillGapAdvisorService. --}}
            @if (! empty($skillRecommendations))
                <x-ui.card>
                    <span class="eyebrow">Level up</span>
                    <h3 class="font-bold text-gray-900 text-lg mt-2">Learn these to unlock more matches</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Skills that show up most in roles you're close to matching — closing one of these gaps could bring more jobs within reach.
                    </p>

                    <ul class="mt-5 space-y-2.5">
                        @foreach ($skillRecommendations as $recommendation)
                            <li class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3">
                                <span class="font-semibold text-gray-900">{{ $recommendation['skill'] }}</span>
                                <span class="shrink-0 text-xs font-semibold text-brand-700 bg-brand-50 rounded-full px-2.5 py-1">
                                    +{{ $recommendation['jobCount'] }} {{ Str::plural('role', $recommendation['jobCount']) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 text-2xs text-gray-400">
                        Estimated from skill overlap across roles you almost match — not a guarantee.
                    </p>
                </x-ui.card>
            @endif

        </div>
    </div>
</x-app-layout>
