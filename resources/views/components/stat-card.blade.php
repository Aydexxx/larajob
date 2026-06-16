@props([
    'label',
    'value',
    'color' => 'brand',
    'href' => null,
    'sublabel' => null,
])

@php
    $valueColors = [
        'brand'  => 'text-brand-600',
        'indigo' => 'text-brand-600',
        'green'  => 'text-green-600',
        'yellow' => 'text-accent-500',
        'red'    => 'text-red-500',
        'blue'   => 'text-blue-600',
        'purple' => 'text-purple-600',
        'gray'   => 'text-gray-900',
        'sky'    => 'text-sky-600',
    ];
    $valueColor = $valueColors[$color] ?? 'text-gray-900';
    $interactive = $href ? ' transition-all duration-200 hover:shadow-elevated hover:border-brand-200 hover:-translate-y-0.5' : '';
@endphp

@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif class="surface p-5 block{{ $interactive }}">
    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
    <p class="text-3xl font-extrabold {{ $valueColor }} mt-1">{{ $value }}</p>

    @if ($sublabel)
        <p class="text-xs text-gray-400 mt-1">{{ $sublabel }}</p>
    @endif

    @if ($href)
        <span class="text-xs font-semibold text-brand-600 mt-2 inline-flex items-center gap-1">
            View
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
        </span>
    @endif
</{{ $tag }}>
