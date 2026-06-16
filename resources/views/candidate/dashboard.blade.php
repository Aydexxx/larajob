<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Candidate Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Welcome + quick actions -->
            <div class="bg-white border border-gray-200 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-gray-900 text-lg">Welcome back, {{ $user->name }}!</h3>
                    <p class="text-sm text-gray-500 mt-1">Find your next opportunity today.</p>
                </div>
                <a href="{{ route('jobs.index') }}"
                    class="inline-flex items-center px-4 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 text-sm shrink-0">
                    Browse Jobs
                </a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <!-- Applications -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                    <p class="text-3xl font-bold text-indigo-600">{{ $applicationCount }}</p>
                    <p class="text-sm text-gray-500 mt-1">Applications Sent</p>
                    <a href="{{ route('candidate.applications.index') }}"
                        class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                        View all →
                    </a>
                </div>

                <!-- Profile completion -->
                @php
                    $completion = $profile ? $profile->completionPercent() : 0;
                    $completionColor = $completion >= 80 ? 'text-green-600' : ($completion >= 40 ? 'text-yellow-600' : 'text-red-500');
                @endphp
                <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                    <p class="text-3xl font-bold {{ $completionColor }}">{{ $completion }}%</p>
                    <p class="text-sm text-gray-500 mt-1">Profile Complete</p>
                    <a href="{{ route('candidate.profile.edit') }}"
                        class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                        {{ $completion < 100 ? 'Complete profile →' : 'Edit profile →' }}
                    </a>
                </div>

                <!-- Resume status -->
                <div class="bg-white border border-gray-200 rounded-xl p-5 text-center">
                    @if ($profile?->resume_path)
                        <p class="text-3xl font-bold text-green-600">✓</p>
                        <p class="text-sm text-gray-500 mt-1">Resume Uploaded</p>
                        <a href="{{ Storage::url($profile->resume_path) }}" target="_blank"
                            class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                            View resume →
                        </a>
                    @else
                        <p class="text-3xl font-bold text-gray-300">–</p>
                        <p class="text-sm text-gray-500 mt-1">No Resume Yet</p>
                        <a href="{{ route('candidate.profile.edit') }}"
                            class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
                            Upload resume →
                        </a>
                    @endif
                </div>
            </div>

            <!-- Profile incomplete banner -->
            @if ($completion < 50)
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                    <div class="text-amber-500 mt-0.5">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-amber-800">Your profile is incomplete.</p>
                        <p class="text-sm text-amber-700 mt-0.5">
                            Complete your profile to stand out to employers.
                            <a href="{{ route('candidate.profile.edit') }}" class="font-medium underline">
                                Complete now →
                            </a>
                        </p>
                    </div>
                </div>
            @endif

            <!-- Recent applications -->
            @if ($recentApplications->isNotEmpty())
                <div class="bg-white border border-gray-200 rounded-xl">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Recent Applications</h3>
                        <a href="{{ route('candidate.applications.index') }}"
                            class="text-sm text-indigo-600 hover:underline">View all</a>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach ($recentApplications as $application)
                            @php
                                $statusColors = [
                                    'pending'  => 'bg-yellow-100 text-yellow-800',
                                    'reviewed' => 'bg-blue-100 text-blue-800',
                                    'accepted' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ];
                                $sc = $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700';
                            @endphp
                            <a href="{{ route('candidate.applications.show', $application) }}"
                                class="flex items-center justify-between px-6 py-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $application->job?->title ?? 'Job removed' }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ $application->job?->company?->name }}
                                        · {{ $application->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $sc }} shrink-0">
                                    {{ ucfirst($application->status) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
