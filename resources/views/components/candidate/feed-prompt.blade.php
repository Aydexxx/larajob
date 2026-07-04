@props([
    'icon' => 'profile',   // profile | spinner
    'title' => '',
    'description' => '',
    'ctaLabel' => null,
    'ctaUrl' => null,
])

{{--
    A prominent, on-brand prompt used for the "For You" feed's non-ranked
    states (complete-your-profile, analysis-in-progress). Deliberately richer
    than a plain empty-state so an unpersonalized feed still feels designed.
--}}
<div class="relative overflow-hidden rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-white shadow-soft">
    <div class="absolute -top-20 -right-16 h-56 w-56 rounded-full bg-brand-200/30 blur-3xl" aria-hidden="true"></div>
    <div class="absolute inset-0 opacity-[0.04]" style="background-image: radial-gradient(circle at 1px 1px, #0b8c87 1px, transparent 0); background-size: 22px 22px;" aria-hidden="true"></div>

    <div class="relative p-8 sm:p-10 flex flex-col sm:flex-row items-start sm:items-center gap-6">
        <div class="shrink-0 h-16 w-16 rounded-2xl bg-white shadow-soft ring-1 ring-brand-100 flex items-center justify-center text-brand-600">
            @if ($icon === 'spinner')
                <svg class="h-8 w-8 motion-safe:animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V1C5.9 1 1 5.9 1 12h3z"></path>
                </svg>
            @else
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <h2 class="text-lg font-bold text-gray-900">{{ $title }}</h2>
            <p class="text-sm text-gray-600 mt-1.5 max-w-xl">{{ $description }}</p>

            <div class="mt-5">
                @isset($action)
                    {{ $action }}
                @elseif ($ctaLabel && $ctaUrl)
                    <x-ui.button :href="$ctaUrl">
                        {{ $ctaLabel }}
                        <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>
</div>
