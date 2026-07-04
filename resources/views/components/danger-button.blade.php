<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-red-600 border border-transparent rounded-xl font-semibold text-sm text-white shadow-sm hover:bg-red-700 active:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 motion-safe:active:scale-[0.97] transition-all duration-150 ease-out disabled:opacity-50 disabled:pointer-events-none']) }}>
    {{ $slot }}
</button>
