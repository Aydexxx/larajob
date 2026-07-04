@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-xl border-gray-300 text-gray-900 placeholder-gray-400 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm']) }}>
