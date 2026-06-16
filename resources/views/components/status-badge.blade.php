@props(['status'])

@php
    $colors = [
        // Job statuses
        'active'   => 'bg-green-100 text-green-800',
        'closed'   => 'bg-red-100 text-red-800',
        'draft'    => 'bg-gray-100 text-gray-700',
        // Application statuses
        'pending'  => 'bg-yellow-100 text-yellow-800',
        'reviewed' => 'bg-blue-100 text-blue-800',
        'accepted' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        // User / company states
        'admin'     => 'bg-purple-100 text-purple-800',
        'employer'  => 'bg-indigo-100 text-indigo-800',
        'candidate' => 'bg-sky-100 text-sky-800',
        'verified'  => 'bg-green-100 text-green-800',
        'unverified'=> 'bg-gray-100 text-gray-600',
        'suspended' => 'bg-red-100 text-red-800',
        'active-user' => 'bg-green-100 text-green-800',
    ];
    $color = $colors[$status] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$color}"]) }}>
    {{ $slot->isEmpty() ? ucfirst(str_replace('-', ' ', $status)) : $slot }}
</span>
