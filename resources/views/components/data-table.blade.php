@props([
    'headers' => [],
    'empty' => 'No records found.',
    'isEmpty' => false,
])

<div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            @if (! empty($headers))
                <thead class="bg-gray-50">
                    <tr>
                        @foreach ($headers as $header)
                            <th scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                {{ $header }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody class="divide-y divide-gray-100">
                @if ($isEmpty)
                    <tr>
                        <td colspan="{{ max(count($headers), 1) }}" class="px-5 py-12 text-center text-sm text-gray-500">
                            {{ $empty }}
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>
</div>
