@props([
    'href' => null,
    'hover' => false,
    'padding' => 'p-6',
])

@php
    $interactive = $href || $hover;
    $classes = 'surface ' . $padding
        . ($interactive ? ' transition-all duration-200 hover:shadow-elevated hover:border-brand-200 hover:-translate-y-0.5' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'block ' . $classes]) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </div>
@endif
