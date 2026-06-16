@props([
    'label',
    'value',
    'color' => 'indigo',
    'href' => null,
    'sublabel' => null,
])

@php
    $valueColors = [
        'indigo' => 'text-indigo-600',
        'green'  => 'text-green-600',
        'yellow' => 'text-yellow-500',
        'red'    => 'text-red-500',
        'blue'   => 'text-blue-600',
        'purple' => 'text-purple-600',
        'gray'   => 'text-gray-900',
        'sky'    => 'text-sky-600',
    ];
    $valueColor = $valueColors[$color] ?? 'text-gray-900';
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-5">
    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
    <p class="text-3xl font-bold {{ $valueColor }} mt-1">{{ $value }}</p>

    @if ($sublabel)
        <p class="text-xs text-gray-400 mt-1">{{ $sublabel }}</p>
    @endif

    @if ($href)
        <a href="{{ $href }}" class="text-xs text-indigo-600 hover:underline mt-2 inline-block">
            View →
        </a>
    @endif
</div>
