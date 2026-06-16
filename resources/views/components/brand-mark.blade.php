@props([
    'href' => null,
    'mono' => false,
])

@php
    $tag = $href ? 'a' : 'div';
    $word = $mono ? 'text-current' : 'text-gray-900';
    $accent = $mono ? 'text-current' : 'text-brand-600';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="relative inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 shadow-glow">
        <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 8.75A2.75 2.75 0 0 1 6.75 6h10.5A2.75 2.75 0 0 1 20 8.75v7.5A2.75 2.75 0 0 1 17.25 19H6.75A2.75 2.75 0 0 1 4 16.25v-7.5Z" stroke="currentColor" stroke-width="1.5" />
            <path d="M9 6V5.25A2.25 2.25 0 0 1 11.25 3h1.5A2.25 2.25 0 0 1 15 5.25V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            <path d="M4 11.25h16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
            <circle cx="12" cy="12.25" r="1.1" fill="currentColor" />
        </svg>
    </span>
    <span class="text-lg font-extrabold tracking-tight {{ $word }}">Lara<span class="{{ $accent }}">Job</span></span>
</{{ $tag }}>
