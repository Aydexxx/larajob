@props([
    'status' => null,
    'color' => 'gray',
    'size' => 'sm',
    'dot' => false,
])

@php
    // Status presets map a domain status to a colour + human label.
    $statusMap = [
        'active'      => ['green', 'Active'],
        'closed'      => ['red', 'Closed'],
        'draft'       => ['gray', 'Draft'],
        'pending'     => ['yellow', 'Pending'],
        'reviewed'    => ['blue', 'Reviewed'],
        'accepted'    => ['green', 'Accepted'],
        'rejected'    => ['red', 'Rejected'],
        'admin'       => ['purple', 'Admin'],
        'employer'    => ['brand', 'Employer'],
        'candidate'   => ['sky', 'Candidate'],
        'verified'    => ['green', 'Verified'],
        'unverified'  => ['gray', 'Unverified'],
        'suspended'   => ['red', 'Suspended'],
        'remote'      => ['green', 'Remote'],
        'full-time'   => ['brand', 'Full Time'],
        'part-time'   => ['blue', 'Part Time'],
        'contract'    => ['purple', 'Contract'],
        'internship'  => ['sky', 'Internship'],
    ];

    $label = null;
    if ($status !== null) {
        [$color, $label] = $statusMap[$status] ?? ['gray', ucfirst(str_replace('-', ' ', $status))];
    }

    $palette = [
        'gray'   => ['bg-gray-100', 'text-gray-700', 'bg-gray-400'],
        'brand'  => ['bg-brand-50', 'text-brand-700', 'bg-brand-500'],
        'green'  => ['bg-green-100', 'text-green-700', 'bg-green-500'],
        'red'    => ['bg-red-100', 'text-red-700', 'bg-red-500'],
        'yellow' => ['bg-yellow-100', 'text-yellow-800', 'bg-yellow-500'],
        'blue'   => ['bg-blue-100', 'text-blue-700', 'bg-blue-500'],
        'purple' => ['bg-purple-100', 'text-purple-700', 'bg-purple-500'],
        'sky'    => ['bg-sky-100', 'text-sky-700', 'bg-sky-500'],
        'accent' => ['bg-accent-100', 'text-accent-700', 'bg-accent-500'],
    ];

    [$bg, $text, $dotColor] = $palette[$color] ?? $palette['gray'];

    $sizing = $size === 'md' ? 'px-3 py-1 text-xs' : 'px-2.5 py-0.5 text-2xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full font-semibold {$bg} {$text} {$sizing}"]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }}"></span>
    @endif
    {{ $slot->isEmpty() ? $label : $slot }}
</span>
