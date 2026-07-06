{{-- Badge role — dipakai desktop & mobile. Param: $user, $sm (bool kecil) --}}
@php $sm = $sm ?? false; @endphp
@if($user->isMaster())
    <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold {{ $sm ? 'px-2 py-0.5' : 'px-2.5 py-1' }} rounded-full">
        <i class="fas fa-shield-alt text-[{{ $sm ? '9' : '10' }}px]"></i> Admin Master
    </span>
@elseif($user->isItTeam())
    <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-800 text-xs font-semibold {{ $sm ? 'px-2 py-0.5' : 'px-2.5 py-1' }} rounded-full">
        <i class="fas fa-headset text-[{{ $sm ? '9' : '10' }}px]"></i> Tim IT
    </span>
@else
    <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 text-xs font-semibold {{ $sm ? 'px-2 py-0.5' : 'px-2.5 py-1' }} rounded-full">
        <i class="fas fa-user text-[{{ $sm ? '9' : '10' }}px]"></i> Operator Daerah
    </span>
@endif
