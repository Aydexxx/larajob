@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold rounded-xl transition-all duration-150 ease-out motion-safe:active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none whitespace-nowrap';

    $sizes = [
        'sm' => 'text-sm px-3.5 py-2',
        'md' => 'text-sm px-5 py-2.5',
        'lg' => 'text-base px-6 py-3',
    ];

    $variants = [
        'primary'   => 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 active:bg-brand-800',
        'secondary' => 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
        'outline'   => 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 hover:border-gray-400',
        'ghost'     => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
        'ghost-light' => 'text-white hover:bg-white/10',
        'danger'    => 'bg-red-600 text-white shadow-sm hover:bg-red-700 active:bg-red-800',
        'white'     => 'bg-white text-brand-700 shadow-sm hover:bg-brand-50',
    ];

    $classes = trim($base . ' ' . ($sizes[$size] ?? $sizes['md']) . ' ' . ($variants[$variant] ?? $variants['primary']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
