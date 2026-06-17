<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Application Detail') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('employer.applications.index', ['job_id' => $application->job_id]) }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
                ← Back to applications
            </a>

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @php
                $statusColors = [
                    'pending'  => 'bg-yellow-100 text-yellow-800',
                    'reviewed' => 'bg-blue-100 text-blue-800',
                    'accepted' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                ];
                $sc = $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700';
                $profile = $application->user->candidateProfile;
            @endphp

            <div class="flex flex-col lg:flex-row gap-6">

                <!-- Left: cover letter + resume -->
                <div class="flex-1 min-w-0 space-y-5">

                    <!-- Job summary bar -->
                    <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs text-gray-500">Applied for</p>
                            <p class="font-semibold text-gray-900">{{ $application->job?->title ?? 'Job removed' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $sc }}">
                                {{ ucfirst($application->status) }}
                            </span>
                            <p class="text-xs text-gray-400">{{ $application->created_at->format('M j, Y') }}</p>
                        </div>
                    </div>

                    <!-- Cover letter -->
                    <div class="bg-white border border-gray-200 rounded-xl">
                        <div class="px-5 py-4 border-b border-gray-100">
                            <h3 class="font-semibold text-gray-900 text-sm">Cover Letter</h3>
                        </div>
                        <div class="p-5 text-sm text-gray-700 whitespace-pre-line leading-relaxed">
                            {{ $application->cover_letter }}
                        </div>
                    </div>

                    <!-- Resume -->
                    @if ($application->resume_path)
                        <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Application Resume</p>
                                <p class="text-xs text-gray-500 mt-0.5">PDF document</p>
                            </div>
                            <a href="{{ route('employer.applications.resume', $application) }}"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium hover:bg-indigo-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download
                            </a>
                        </div>
                    @endif

                </div>

                <!-- Right: candidate info + status actions -->
                <aside class="lg:w-72 shrink-0 space-y-5">

                    {{-- AI match breakdown — hidden entirely when AI is disabled --}}
                    @if ($matchEnabled)
                        <x-match-card
                            :endpoint="route('employer.applications.match', $application)"
                            :result="$matchInitial"
                            :incomplete="$matchIncomplete" />
                    @endif

                    <!-- Candidate card -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5">
                        <h3 class="font-semibold text-gray-900 text-sm mb-4">Candidate</h3>

                        <div class="space-y-2 text-sm">
                            <p class="font-medium text-gray-900">{{ $application->user->name }}</p>
                            <p class="text-gray-500 text-xs">{{ $application->user->email }}</p>

                            @if ($profile?->headline)
                                <p class="text-gray-700">{{ $profile->headline }}</p>
                            @endif

                            @if ($profile?->location)
                                <p class="text-xs text-gray-500">{{ $profile->location }}</p>
                            @endif

                            @if ($profile?->experience_years !== null)
                                <p class="text-xs text-gray-500">
                                    {{ $profile->experience_years }}
                                    {{ Str::plural('year', $profile->experience_years) }} experience
                                </p>
                            @endif

                            @if ($profile?->skills)
                                <div class="pt-2">
                                    <p class="text-xs font-medium text-gray-500 mb-1.5">Skills</p>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (array_slice(array_map('trim', explode(',', $profile->skills)), 0, 8) as $skill)
                                            @if ($skill)
                                                <span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">
                                                    {{ $skill }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($profile?->linkedin_url)
                                <a href="{{ $profile->linkedin_url }}" target="_blank" rel="noopener noreferrer"
                                    class="inline-block text-xs text-indigo-600 hover:underline pt-1">
                                    LinkedIn Profile →
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Status change -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5">
                        <h3 class="font-semibold text-gray-900 text-sm mb-4">Update Status</h3>

                        <div class="space-y-2">
                            @foreach (['reviewed' => ['label' => 'Mark Reviewed', 'color' => 'border-blue-300 text-blue-700 hover:bg-blue-50'], 'accepted' => ['label' => 'Accept', 'color' => 'border-green-300 text-green-700 hover:bg-green-50'], 'rejected' => ['label' => 'Reject', 'color' => 'border-red-300 text-red-700 hover:bg-red-50']] as $status => $btn)
                                <form method="POST"
                                    action="{{ route('employer.applications.update-status', $application) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status }}" />
                                    <button type="submit"
                                        {{ $application->status === $status ? 'disabled' : '' }}
                                        class="w-full py-2 px-4 rounded-lg border text-sm font-medium transition
                                            {{ $application->status === $status
                                                ? 'opacity-50 cursor-not-allowed bg-gray-50 border-gray-200 text-gray-400'
                                                : $btn['color'] }}">
                                        {{ $btn['label'] }}
                                        @if ($application->status === $status)
                                            <span class="text-xs">(current)</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
