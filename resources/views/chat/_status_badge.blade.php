@php
    $s = match($status) {
        'open'     => 'bg-green-100 text-green-700',
        'pending'  => 'bg-yellow-100 text-yellow-700',
        'resolved' => 'bg-blue-100 text-blue-700',
        'closed'   => 'bg-gray-200 text-gray-600',
        default    => 'bg-gray-100 text-gray-600',
    };
@endphp
<span class="inline-flex items-center text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $s }}">{{ $label }}</span>
