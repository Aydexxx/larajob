@props([
    'name' => null,
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'error' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $error ??= ($name ? $errors->first($name) : null);
    $hasIcon = isset($icon);

    $inputClasses = 'block w-full rounded-xl border-gray-300 text-gray-900 placeholder-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm'
        . ($hasIcon ? ' pl-10' : '')
        . ($error ? ' border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' : '');
@endphp

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }}
            @if ($attributes->get('required'))
                <span class="text-red-500" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    <div class="relative">
        @if ($hasIcon)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                {{ $icon }}
            </span>
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            value="{{ $value }}"
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->merge(['class' => $inputClasses]) }} />
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-red-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
