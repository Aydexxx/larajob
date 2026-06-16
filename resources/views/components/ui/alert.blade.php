@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $variants = [
        'success' => [
            'wrap' => 'bg-green-50 border-green-200 text-green-800',
            'icon' => 'text-green-500',
            'path' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        ],
        'error' => [
            'wrap' => 'bg-red-50 border-red-200 text-red-800',
            'icon' => 'text-red-500',
            'path' => 'M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z',
        ],
        'warning' => [
            'wrap' => 'bg-accent-50 border-accent-200 text-accent-700',
            'icon' => 'text-accent-500',
            'path' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
        ],
        'info' => [
            'wrap' => 'bg-brand-50 border-brand-200 text-brand-800',
            'icon' => 'text-brand-500',
            'path' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z',
        ],
    ];

    $cfg = $variants[$variant] ?? $variants['info'];
@endphp

<div
    @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition.opacity @endif
    {{ $attributes->merge(['class' => "flex items-start gap-3 rounded-xl border p-4 {$cfg['wrap']}"]) }}
    role="alert">
    <svg class="h-5 w-5 shrink-0 mt-0.5 {{ $cfg['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $cfg['path'] }}" />
    </svg>

    <div class="flex-1 min-w-0 text-sm">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button" @click="show = false" class="shrink-0 -m-1 p-1 rounded-lg opacity-60 hover:opacity-100 transition" aria-label="Dismiss">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
