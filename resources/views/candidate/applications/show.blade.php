<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Application Detail') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('candidate.applications.index') }}"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-6">
                ← Back to applications
            </a>

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @php
                $statusColors = [
                    'pending'  => 'bg-yellow-100 text-yellow-800',
                    'reviewed' => 'bg-blue-100 text-blue-800',
                    'accepted' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800',
                ];
                $color = $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700';
            @endphp

            <!-- Job summary -->
            <div class="bg-white border border-gray-200 rounded-xl p-5 mb-5 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    @if ($application->job?->company?->logo)
                        <img src="{{ Storage::url($application->job->company->logo) }}"
                            alt="{{ $application->job->company->name }}"
                            class="h-12 w-12 rounded-lg object-cover border border-gray-100 shrink-0" />
                    @else
                        <div class="h-12 w-12 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0">
                            <span class="font-bold text-indigo-600">
                                {{ mb_strtoupper(mb_substr($application->job?->company?->name ?? '?', 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    <div>
                        <h3 class="font-semibold text-gray-900">
                            {{ $application->job?->title ?? 'Job removed' }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $application->job?->company?->name }}
                            @if ($application->job?->location) · {{ $application->job->location }} @endif
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $color }} shrink-0">
                    {{ ucfirst($application->status) }}
                </span>
            </div>

            <!-- Application content -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm divide-y divide-gray-100">

                <!-- Cover letter -->
                <div class="p-6">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3">Cover Letter</h4>
                    <div class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">
                        {{ $application->cover_letter }}
                    </div>
                </div>

                <!-- Resume -->
                @if ($application->resume_path)
                    <div class="p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-3">Resume</h4>
                        <a href="{{ Storage::url($application->resume_path) }}"
                            target="_blank"
                            class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:underline">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            View submitted resume
                        </a>
                    </div>
                @endif

                <!-- Meta -->
                <div class="px-6 py-4 flex items-center justify-between">
                    <p class="text-xs text-gray-400">
                        Applied {{ $application->created_at->format('M j, Y \a\t g:i A') }}
                    </p>

                    @if ($application->status === 'pending')
                        <form method="POST"
                            action="{{ route('candidate.applications.destroy', $application) }}"
                            onsubmit="return confirm('Withdraw this application?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-xs px-3 py-1.5 border border-red-300 text-red-600 rounded-md font-medium hover:bg-red-50">
                                Withdraw Application
                            </button>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
