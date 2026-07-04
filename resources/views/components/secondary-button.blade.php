<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-white border border-gray-300 rounded-xl font-semibold text-sm text-gray-700 shadow-sm hover:bg-gray-50 hover:border-gray-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none transition-all duration-150 ease-out']) }}>
    {{ $slot }}
</button>
