@props([
    'job',
    'layout' => 'grid',
])

@php
    $company = $job->company;
    $logo = $company?->logo ? Storage::url($company->logo) : null;

    $salary = null;
    if ($job->salary_min && $job->salary_max) {
        $salary = '$' . number_format($job->salary_min / 1000) . 'k – $' . number_format($job->salary_max / 1000) . 'k';
    } elseif ($job->salary_min) {
        $salary = 'From $' . number_format($job->salary_min / 1000) . 'k';
    } elseif ($job->salary_max) {
        $salary = 'Up to $' . number_format($job->salary_max / 1000) . 'k';
    }
@endphp

@if ($layout === 'row')
    <a href="{{ route('jobs.show', $job) }}"
        class="surface group block p-5 transition-all duration-200 hover:shadow-elevated hover:border-brand-200">
        <div class="flex items-start gap-4">
            <x-ui.avatar :name="$company?->name ?? '?'" :src="$logo" size="md" />

            <div class="flex-1 min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 sm:gap-3">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 leading-snug group-hover:text-brand-700 transition-colors">
                            {{ $job->title }}
                        </h3>
                        <p class="text-sm text-gray-500 mt-0.5 truncate">{{ $company?->name }}</p>
                    </div>
                    <span class="text-xs text-gray-400 shrink-0 whitespace-nowrap">
                        {{ $job->created_at->diffForHumans() }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <x-ui.badge :status="$job->type" />
                    @if ($job->is_remote)
                        <x-ui.badge color="green" dot>Remote</x-ui.badge>
                    @endif
                    @if ($job->location)
                        <x-ui.badge color="gray">{{ $job->location }}</x-ui.badge>
                    @endif
                    @if ($salary)
                        <x-ui.badge color="accent">{{ $salary }}</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>
    </a>
@else
    <a href="{{ route('jobs.show', $job) }}"
        class="surface group flex flex-col p-5 h-full transition-all duration-200 hover:shadow-elevated hover:border-brand-200 hover:-translate-y-0.5">
        <div class="flex items-center gap-3 mb-4">
            <x-ui.avatar :name="$company?->name ?? '?'" :src="$logo" size="sm" />
            <p class="text-sm text-gray-500 truncate">{{ $company?->name }}</p>
        </div>

        <h3 class="font-semibold text-gray-900 leading-snug mb-3 group-hover:text-brand-700 transition-colors">
            {{ $job->title }}
        </h3>

        <div class="flex flex-wrap gap-2 mb-4">
            <x-ui.badge :status="$job->type" />
            @if ($job->is_remote)
                <x-ui.badge color="green" dot>Remote</x-ui.badge>
            @endif
            @if ($job->location)
                <x-ui.badge color="gray">{{ $job->location }}</x-ui.badge>
            @endif
        </div>

        <div class="flex items-center justify-between text-xs mt-auto pt-3 border-t border-gray-100">
            <span class="font-semibold text-gray-700">{{ $salary ?? 'Competitive' }}</span>
            <span class="text-gray-400">{{ $job->created_at->diffForHumans() }}</span>
        </div>
    </a>
@endif
