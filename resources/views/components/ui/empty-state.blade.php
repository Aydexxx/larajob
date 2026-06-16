@props([
    'title' => 'Nothing here yet',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'text-center py-14 px-6']) }}>
    <div class="mx-auto h-14 w-14 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 mb-4">
        @isset($icon)
            {{ $icon }}
        @else
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
            </svg>
        @endisset
    </div>

    <h3 class="text-sm font-semibold text-gray-900">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 text-sm text-gray-500 max-w-sm mx-auto">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-5 flex justify-center">{{ $action }}</div>
    @endisset
</div>
