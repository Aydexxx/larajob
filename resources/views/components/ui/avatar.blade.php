@props([
    'name' => '?',
    'src' => null,
    'size' => 'md',
    'square' => true,
])

@php
    $sizes = [
        'xs' => 'h-8 w-8 text-xs',
        'sm' => 'h-10 w-10 text-sm',
        'md' => 'h-12 w-12 text-base',
        'lg' => 'h-16 w-16 text-xl',
        'xl' => 'h-20 w-20 text-2xl',
    ];
    $dimension = $sizes[$size] ?? $sizes['md'];
    $shape = $square ? 'rounded-xl' : 'rounded-full';
    $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? $name : '?', 0, 1));
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        alt="{{ $name }} logo"
        {{ $attributes->merge(['class' => "{$dimension} {$shape} object-cover border border-gray-100 shrink-0 bg-white"]) }} />
@else
    <div
        aria-hidden="true"
        {{ $attributes->merge(['class' => "{$dimension} {$shape} shrink-0 flex items-center justify-center font-bold bg-brand-100 text-brand-700"]) }}>
        {{ $initial }}
    </div>
@endif
