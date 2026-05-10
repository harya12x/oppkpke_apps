<button onclick="toggleActive({{ $user->id }}, '{{ addslashes($user->name) }}')"
        id="toggle-{{ $user->id }}"
        class="relative inline-flex items-center h-6 rounded-full w-11 transition focus:outline-none
            {{ $user->id === auth()->id() ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}
            {{ $user->is_active ? 'bg-green-500' : 'bg-gray-300' }}"
        {{ $user->id === auth()->id() ? 'disabled' : '' }}>
    <span id="toggle-dot-{{ $user->id }}"
          class="inline-block w-4 h-4 transform bg-white rounded-full transition
              {{ $user->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
</button>
<p id="toggle-label-{{ $user->id }}"
   class="text-xs mt-0.5 {{ $user->is_active ? 'text-green-600' : 'text-gray-400' }}">
    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
</p>
