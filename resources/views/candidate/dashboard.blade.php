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
                    @if ($profile?->resume_path)
                        <p class="text-3xl font-extrabold text-green-600">✓</p>
                        <p class="text-sm text-gray-500 mt-1">Resume uploaded</p>
                        <a href="{{ Storage::url($profile->resume_path) }}" target="_blank" class="text-xs font-semibold text-brand-600 hover:text-brand-700 mt-2 inline-block">View resume →</a>
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

        </div>
    </div>
</x-app-layout>
