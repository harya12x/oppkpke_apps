<div class="flex items-center justify-center gap-1.5">
    <button onclick="openEditModal({{ $user->id }})" title="Edit"
            class="w-8 h-8 flex items-center justify-center bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg transition">
        <i class="fas fa-pen text-xs"></i>
    </button>
    <button onclick="openResetModal({{ $user->id }}, '{{ addslashes($user->name) }}')" title="Reset password"
            class="w-8 h-8 flex items-center justify-center bg-orange-50 hover:bg-orange-100 text-orange-600 rounded-lg transition">
        <i class="fas fa-key text-xs"></i>
    </button>
    @if($user->id !== auth()->id())
    <button onclick="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')" title="Hapus"
            class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition">
        <i class="fas fa-trash text-xs"></i>
    </button>
    @endif
</div>
