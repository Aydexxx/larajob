<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Applications') }}
        </h2>
    </x-slot>

    <div class="py-10 bg-gray-50 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('info'))
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm">
                    {{ session('info') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            @forelse ($applications as $application)
                @php
                    $statusColors = [
                        'pending'  => 'bg-yellow-100 text-yellow-800',
                        'reviewed' => 'bg-blue-100 text-blue-800',
                        'accepted' => 'bg-green-100 text-green-800',
                        'rejected' => 'bg-red-100 text-red-800',
                    ];
                    $color = $statusColors[$application->status] ?? 'bg-gray-100 text-gray-700';
                @endphp

                <div class="bg-white border border-gray-200 rounded-xl p-5 mb-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4 min-w-0">
                            <!-- Company logo -->
                            @if ($application->job?->company?->logo)
                                <img src="{{ Storage::url($application->job->company->logo) }}"
                                    alt="{{ $application->job->company->name }}"
                                    class="h-11 w-11 rounded-lg object-cover border border-gray-100 shrink-0" />
                            @else
                                <div class="h-11 w-11 rounded-lg bg-brand-100 flex items-center justify-center shrink-0">
                                    <span class="font-bold text-brand-600 text-sm">
                                        {{ mb_strtoupper(mb_substr($application->job?->company?->name ?? '?', 0, 1)) }}
                                    </span>
                                </div>
                            @endif

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-900">
                                        {{ $application->job?->title ?? 'Job removed' }}
                                    </h3>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                                        {{ ucfirst($application->status) }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    {{ $application->job?->company?->name }}
                                    @if ($application->job?->location)
                                        · {{ $application->job->location }}
                                    @endif
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Applied {{ $application->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 shrink-0">
                            <a href="{{ route('candidate.applications.show', $application) }}"
                                class="text-xs px-3 py-1.5 border border-brand-300 text-brand-600 rounded-md font-medium hover:bg-brand-50">
                                View
                            </a>

                            @if ($application->status === 'pending')
                                <form method="POST"
                                    action="{{ route('candidate.applications.destroy', $application) }}"
                                    onsubmit="return confirm('Withdraw this application?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="text-xs px-3 py-1.5 border border-gray-300 text-gray-600 rounded-md font-medium hover:bg-gray-50">
                                        Withdraw
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <x-ui.card>
                    <x-ui.empty-state
                        context="applications"
                        title="No applications yet"
                        description="Once you apply to a role, you'll be able to track its status and hear back from employers right here.">
                        <x-slot name="action">
                            <x-ui.button :href="route('jobs.index')">Browse jobs</x-ui.button>
                        </x-slot>
                    </x-ui.empty-state>
                </x-ui.card>
            @endforelse

        </div>
    </div>
</x-app-layout>
