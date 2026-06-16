@php
    $toasts = [];
    if (session('success')) {
        $toasts[] = ['type' => 'success', 'message' => session('success')];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'message' => session('error')];
    }
    if (session('status')) {
        $toasts[] = ['type' => 'info', 'message' => session('status')];
    }

    $styles = [
        'success' => ['bar' => 'bg-green-500', 'icon' => 'text-green-500', 'path' => 'M9 12.75 11.25 15 15 9.75'],
        'error'   => ['bar' => 'bg-red-500', 'icon' => 'text-red-500', 'path' => 'M6 18 18 6M6 6l12 12'],
        'info'    => ['bar' => 'bg-brand-500', 'icon' => 'text-brand-500', 'path' => 'M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M12 8.25h.008v.008H12V8.25Z'],
    ];
@endphp

@if (count($toasts))
    <div class="fixed top-4 inset-x-0 z-[60] flex flex-col items-center gap-2 px-4 pointer-events-none" aria-live="polite">
        @foreach ($toasts as $toast)
            @php $s = $styles[$toast['type']] ?? $styles['info']; @endphp
            <div
                x-data="{ show: true }"
                x-show="show"
                x-init="setTimeout(() => show = false, 5000)"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 -translate-y-3"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0 -translate-y-2"
                class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-elevated ring-1 ring-gray-900/5 flex">
                <div class="w-1 {{ $s['bar'] }}"></div>
                <div class="flex items-start gap-3 p-4 flex-1">
                    <svg class="h-5 w-5 shrink-0 mt-0.5 {{ $s['icon'] }}" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['path'] }}" />
                        @if ($toast['type'] !== 'error')
                            <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                        @endif
                    </svg>
                    <p class="text-sm font-medium text-gray-800 flex-1">{{ $toast['message'] }}</p>
                    <button type="button" @click="show = false" class="shrink-0 -m-1 p-1 rounded-lg text-gray-400 hover:text-gray-600 transition" aria-label="Dismiss">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
@endif
