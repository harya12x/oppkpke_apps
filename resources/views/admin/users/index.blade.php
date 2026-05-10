@extends('layouts.oppkpke')

@section('title', 'Kelola Pengguna')
@section('page-title', 'Kelola Pengguna')
@section('page-subtitle', 'Manajemen akun operator seluruh perangkat daerah')

@section('content')

{{-- ── SUMMARY CARDS ──────────────────────────────────────── --}}
<div class="grid grid-cols-3 md:grid-cols-5 gap-2 md:gap-4 mb-4 md:mb-6">
    <div class="bg-white rounded-xl border shadow-sm p-3 md:p-4 text-center">
        <p class="text-xl md:text-2xl font-bold text-gray-800">{{ $summary['total'] }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total</p>
    </div>
    <div class="bg-yellow-50 rounded-xl border border-yellow-200 shadow-sm p-3 md:p-4 text-center">
        <p class="text-xl md:text-2xl font-bold text-yellow-700">{{ $summary['master'] }}</p>
        <p class="text-xs text-yellow-600 mt-0.5">Master</p>
    </div>
    <div class="bg-blue-50 rounded-xl border border-blue-200 shadow-sm p-3 md:p-4 text-center">
        <p class="text-xl md:text-2xl font-bold text-blue-700">{{ $summary['daerah'] }}</p>
        <p class="text-xs text-blue-600 mt-0.5">Operator</p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-3 md:p-4 text-center">
        <p class="text-xl md:text-2xl font-bold text-green-700">{{ $summary['active'] }}</p>
        <p class="text-xs text-green-600 mt-0.5">Aktif</p>
    </div>
    <div class="bg-red-50 rounded-xl border border-red-200 shadow-sm p-3 md:p-4 text-center">
        <p class="text-xl md:text-2xl font-bold text-red-700">{{ $summary['inactive'] }}</p>
        <p class="text-xs text-red-600 mt-0.5">Nonaktif</p>
    </div>
</div>

{{-- ── TOOLBAR ─────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border shadow-sm p-3 md:p-4 mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" id="filterForm"
          class="flex flex-wrap gap-2 md:gap-3 items-end">

        {{-- Search --}}
        <div class="flex-1 min-w-[160px]">
            <label class="text-xs font-medium text-gray-600 block mb-1">Cari nama / email</label>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nama atau email..."
                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        {{-- Role Filter --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Role</label>
            <input type="hidden" id="filterRole" name="role" value="{{ request('role') }}">
            <button type="button" id="filterRoleBtn"
                    onclick="openPicker('role','filter')"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition min-w-[130px]">
                <i class="fas fa-user-tag text-gray-400 text-xs"></i>
                <span id="filterRoleBtnLabel" class="text-gray-700 flex-1 text-left">
                    @if(request('role') === 'master') Admin Master
                    @elseif(request('role') === 'daerah') Operator Daerah
                    @else Semua Role
                    @endif
                </span>
                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
        </div>

        {{-- Perangkat Daerah Filter --}}
        <div class="hidden md:block">
            <label class="text-xs font-medium text-gray-600 block mb-1">Perangkat Daerah</label>
            <input type="hidden" id="filterPd" name="perangkat_daerah_id" value="{{ request('perangkat_daerah_id') }}">
            <button type="button" id="filterPdBtn"
                    onclick="openPicker('pd','filter')"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition min-w-[150px] max-w-[200px]">
                <i class="fas fa-building text-gray-400 text-xs"></i>
                <span id="filterPdBtnLabel" class="text-gray-700 flex-1 text-left truncate">
                    @php
                        $selPd = $perangkatDaerah->firstWhere('id', request('perangkat_daerah_id'));
                    @endphp
                    {{ $selPd ? ($selPd->singkatan ?? $selPd->nama) : 'Semua Perangkat' }}
                </span>
                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
        </div>

        {{-- Status Filter --}}
        <div>
            <label class="text-xs font-medium text-gray-600 block mb-1">Status</label>
            <input type="hidden" id="filterStatus" name="status" value="{{ request('status') }}">
            <button type="button" id="filterStatusBtn"
                    onclick="openPicker('status','filter')"
                    class="flex items-center gap-2 border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition min-w-[110px]">
                <i class="fas fa-circle text-gray-400 text-xs"></i>
                <span id="filterStatusBtnLabel" class="text-gray-700 flex-1 text-left">
                    @if(request('status') === 'active') Aktif
                    @elseif(request('status') === 'inactive') Nonaktif
                    @else Semua Status
                    @endif
                </span>
                <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
            </button>
        </div>

        <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-3 md:px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1.5">
            <i class="fas fa-filter"></i> <span class="hidden sm:inline">Filter</span>
        </button>
        <a href="{{ route('admin.users.index') }}"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 md:px-4 py-2 rounded-lg text-sm font-medium transition">
            Reset
        </a>

        <div class="ml-auto">
            <button type="button" onclick="openCreateModal()"
                    class="bg-green-600 hover:bg-green-700 text-white px-3 md:px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-1.5">
                <i class="fas fa-user-plus"></i>
                <span class="hidden sm:inline">Tambah Pengguna</span>
                <span class="sm:hidden">Tambah</span>
            </button>
        </div>
    </form>
</div>

{{-- ── TABEL PENGGUNA ───────────────────────────────────────── --}}
<div class="bg-white rounded-xl border shadow-sm overflow-hidden">
    <div class="px-4 md:px-6 py-3 border-b bg-gray-50">
        <span class="text-xs md:text-sm text-gray-600 font-medium">
            Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }}
            dari {{ $users->total() }} pengguna
        </span>
    </div>

    {{-- Desktop table --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Pengguna</th>
                    <th class="px-5 py-3 text-left">Role</th>
                    <th class="px-5 py-3 text-left">Perangkat Daerah</th>
                    <th class="px-5 py-3 text-center">Status</th>
                    <th class="px-5 py-3 text-center">Login Terakhir</th>
                    <th class="px-5 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50 transition {{ !$user->is_active ? 'opacity-60' : '' }}" id="row-{{ $user->id }}">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0
                                {{ $user->isMaster() ? 'bg-yellow-400 text-yellow-900' : 'bg-blue-100 text-blue-700' }}">
                                {{ $user->initials }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-800">
                                    {{ $user->name }}
                                    @if($user->id === auth()->id())
                                        <span class="ml-1 text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Anda</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($user->isMaster())
                            <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="fas fa-shield-alt text-[10px]"></i> Admin Master
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <i class="fas fa-user text-[10px]"></i> Operator Daerah
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($user->perangkatDaerah)
                            <span class="text-gray-700 text-sm">{{ $user->perangkatDaerah->nama }}</span>
                            <p class="text-xs text-gray-400 capitalize">{{ $user->perangkatDaerah->jenis }}</p>
                        @else
                            <span class="text-gray-400 italic text-xs">— Semua Daerah —</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @include('admin.users._toggle', ['user' => $user])
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @if($user->last_login_at)
                            <span class="text-xs text-gray-600">{{ $user->last_login_at->format('d M Y') }}</span>
                            <p class="text-xs text-gray-400">{{ $user->last_login_at->format('H:i') }}</p>
                        @else
                            <span class="text-xs text-gray-400 italic">Belum pernah</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @include('admin.users._actions', ['user' => $user])
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center text-gray-400">
                        <i class="fas fa-users text-4xl mb-3 block"></i>
                        <p class="font-medium">Tidak ada pengguna ditemukan</p>
                        <p class="text-sm mt-1">Coba ubah filter atau buat pengguna baru</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Mobile card list --}}
    <div class="md:hidden divide-y divide-gray-100">
        @forelse($users as $user)
        <div class="p-4 {{ !$user->is_active ? 'opacity-60' : '' }}" id="row-{{ $user->id }}">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0
                    {{ $user->isMaster() ? 'bg-yellow-400 text-yellow-900' : 'bg-blue-100 text-blue-700' }}">
                    {{ $user->initials }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 truncate">
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span class="ml-1 text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Anda</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        </div>
                        <div class="flex items-center gap-1 flex-shrink-0">
                            @include('admin.users._actions', ['user' => $user])
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        @if($user->isMaster())
                            <span class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-xs font-semibold px-2 py-0.5 rounded-full">
                                <i class="fas fa-shield-alt text-[9px]"></i> Admin Master
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 text-xs font-semibold px-2 py-0.5 rounded-full">
                                <i class="fas fa-user text-[9px]"></i> Operator Daerah
                            </span>
                        @endif
                        @include('admin.users._toggle', ['user' => $user])
                    </div>
                    @if($user->perangkatDaerah)
                    <p class="text-xs text-gray-500 mt-1">
                        <i class="fas fa-building mr-1 text-gray-400"></i>{{ $user->perangkatDaerah->nama }}
                    </p>
                    @endif
                    @if($user->last_login_at)
                    <p class="text-xs text-gray-400 mt-0.5">
                        <i class="fas fa-clock mr-1"></i>Login: {{ $user->last_login_at->format('d M Y H:i') }}
                    </p>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="py-16 text-center text-gray-400">
            <i class="fas fa-users text-4xl mb-3 block"></i>
            <p class="font-medium">Tidak ada pengguna ditemukan</p>
        </div>
        @endforelse
    </div>

    @if($users->hasPages())
    <div class="px-4 md:px-6 py-4 border-t">
        {{ $users->links() }}
    </div>
    @endif
</div>


{{-- ════════════════════════════════════════════════════════════
     MODAL: CREATE / EDIT USER
════════════════════════════════════════════════════════════ --}}
<div id="modalUser" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalUser')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-4 flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm md:text-base">
                <i class="fas fa-user-edit"></i>
                <span id="modalUserTitle">Tambah Pengguna</span>
            </h3>
            <button onclick="closeModal('modalUser')" class="text-white/70 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="formUser" class="p-4 md:p-5 space-y-4 overflow-y-auto max-h-[80vh]">
            @csrf
            <input type="hidden" id="userId" name="_user_id">
            <div id="formError" class="hidden bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"></div>

            {{-- Nama --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nama Lengkap <span class="text-red-500">*</span>
                </label>
                <input type="text" id="fieldName" name="name" placeholder="Nama lengkap" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Email --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" id="fieldEmail" name="email" placeholder="contoh@email.com" autocomplete="off"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            {{-- Password --}}
            <div id="wrapPassword">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Password <span class="text-red-500" id="passRequired">*</span>
                </label>
                <div class="relative">
                    <input type="password" id="fieldPassword" name="password" placeholder="Min. 8 karakter" autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 pr-9">
                    <button type="button" onclick="togglePwd('fieldPassword','eyePass')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-eye text-sm" id="eyePass"></i>
                    </button>
                </div>
                <p id="passHint" class="text-xs text-gray-400 mt-1 hidden">Kosongkan jika tidak ingin mengubah password.</p>
            </div>

            {{-- Role Picker --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Role <span class="text-red-500">*</span>
                </label>
                <input type="hidden" id="fieldRole" name="role">
                <button type="button" id="fieldRoleBtn"
                        onclick="openPicker('role','form')"
                        class="w-full flex items-center gap-3 border border-gray-300 rounded-lg px-3 py-2.5 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition text-left">
                    <div id="fieldRoleBtnIcon" class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user-tag text-gray-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="fieldRoleBtnLabel" class="text-sm text-gray-500">Pilih role pengguna...</p>
                        <p id="fieldRoleBtnDesc"  class="text-xs text-gray-400 hidden"></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 text-xs flex-shrink-0"></i>
                </button>
            </div>

            {{-- Perangkat Daerah Picker --}}
            <div id="wrapPd">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Perangkat Daerah <span class="text-red-500">*</span>
                </label>
                <input type="hidden" id="fieldPd" name="perangkat_daerah_id">
                <button type="button" id="fieldPdBtn"
                        onclick="openPicker('pd','form')"
                        class="w-full flex items-center gap-3 border border-gray-300 rounded-lg px-3 py-2.5 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition text-left">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-building text-gray-400"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p id="fieldPdBtnLabel" class="text-sm text-gray-500">Pilih perangkat daerah...</p>
                        <p id="fieldPdBtnSub"   class="text-xs text-gray-400 hidden"></p>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400 text-xs flex-shrink-0"></i>
                </button>

                {{-- Warning: PD sudah ada operator --}}
                <div id="pdWarning" class="hidden mt-2 bg-orange-50 border border-orange-200 text-orange-700 rounded-lg px-3 py-2 text-xs flex items-start gap-2">
                    <i class="fas fa-exclamation-triangle mt-0.5 flex-shrink-0"></i>
                    <span id="pdWarningText"></span>
                </div>
            </div>

            {{-- Status Aktif --}}
            <div class="flex items-center gap-3 pt-1">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="fieldActive" name="is_active" value="1" checked class="sr-only peer">
                    <div class="w-10 h-5 bg-gray-300 rounded-full peer peer-checked:bg-green-500 transition"></div>
                    <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full transition peer-checked:translate-x-5"></div>
                </label>
                <label for="fieldActive" class="text-sm text-gray-700 cursor-pointer">Akun Aktif</label>
            </div>

            {{-- Buttons --}}
            <div class="flex gap-2 md:gap-3 pt-2">
                <button type="button" onclick="closeModal('modalUser')"
                        class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm font-medium hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i>
                    <span id="btnSubmitLabel">Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ════ MODAL: RESET PASSWORD ════ --}}
<div id="modalReset" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalReset')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-5 py-4 flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm">
                <i class="fas fa-key"></i> Reset Password
            </h3>
            <button onclick="closeModal('modalReset')" class="text-white/70 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-4 md:p-6 space-y-4">
            <p class="text-sm text-gray-600">
                Reset password untuk: <strong id="resetUserName" class="text-gray-800"></strong>
            </p>
            <div id="resetError" class="hidden bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"></div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Password Baru <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" id="newPassword" placeholder="Min. 8 karakter (huruf + angka)"
                           autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-500 pr-9">
                    <button type="button" onclick="togglePwd('newPassword','eyeReset')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-eye text-sm" id="eyeReset"></i>
                    </button>
                </div>
            </div>
            <div class="flex gap-2 md:gap-3">
                <button onclick="closeModal('modalReset')"
                        class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
                <button onclick="submitReset()"
                        class="flex-1 bg-orange-500 hover:bg-orange-600 text-white py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-save mr-1"></i> Reset
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ════ MODAL: KONFIRMASI HAPUS ════ --}}
<div id="modalDelete" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalDelete')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 p-5 md:p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-trash-alt text-red-500 text-2xl"></i>
        </div>
        <h3 class="font-semibold text-gray-800 text-base mb-1">Hapus Pengguna?</h3>
        <p class="text-sm text-gray-500 mb-1">Akun <strong id="deleteUserName" class="text-gray-700"></strong> akan dihapus permanen.</p>
        <p class="text-xs text-red-500 mb-5">Tindakan ini tidak dapat dibatalkan.</p>
        <div class="flex gap-2 md:gap-3">
            <button onclick="closeModal('modalDelete')"
                    class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
            <button onclick="confirmDelete()"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-sm font-semibold transition">
                Ya, Hapus
            </button>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════
     PICKER MODAL: ROLE
════════════════════════════════════════════════════════════ --}}
<div id="modalPickerRole" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" onclick="closePicker('role')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-user-tag text-blue-600"></i> Pilih Role
            </h3>
            <button onclick="closePicker('role')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 space-y-2.5" id="rolePickerOptions">
            {{-- "Semua" only shown in filter mode --}}
            <div id="rolePickerAllOption" class="hidden">
                <button type="button" onclick="selectPickerRole('','Semua Role')"
                        class="w-full flex items-center gap-3 p-3.5 rounded-xl border-2 border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition text-left group"
                        data-value="">
                    <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-blue-100">
                        <i class="fas fa-users text-gray-500 group-hover:text-blue-600"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-700">Semua Role</p>
                        <p class="text-xs text-gray-400">Tampilkan semua pengguna</p>
                    </div>
                </button>
            </div>
            <button type="button" onclick="selectPickerRole('daerah','Operator Daerah')"
                    class="w-full flex items-center gap-3 p-3.5 rounded-xl border-2 border-gray-200 hover:border-blue-400 hover:bg-blue-50 transition text-left group"
                    data-value="daerah">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-blue-200">
                    <i class="fas fa-user text-blue-600"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800">Operator Daerah</p>
                    <p class="text-xs text-gray-500">Dapat input laporan untuk perangkat daerahnya</p>
                </div>
                <i class="fas fa-check text-blue-500 ml-auto hidden" id="roleCheckDaerah"></i>
            </button>
            <button type="button" onclick="selectPickerRole('master','Admin Master')"
                    class="w-full flex items-center gap-3 p-3.5 rounded-xl border-2 border-gray-200 hover:border-yellow-400 hover:bg-yellow-50 transition text-left group"
                    data-value="master">
                <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center flex-shrink-0 group-hover:bg-yellow-200">
                    <i class="fas fa-shield-alt text-yellow-600"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800">Admin Master</p>
                    <p class="text-xs text-gray-500">Akses penuh ke seluruh data dan administrasi</p>
                </div>
                <i class="fas fa-check text-yellow-500 ml-auto hidden" id="roleCheckMaster"></i>
            </button>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════
     PICKER MODAL: STATUS
════════════════════════════════════════════════════════════ --}}
<div id="modalPickerStatus" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" onclick="closePicker('status')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xs z-10 overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-circle text-green-500"></i> Pilih Status
            </h3>
            <button onclick="closePicker('status')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-4 space-y-2">
            <button type="button" onclick="selectPickerStatus('','Semua Status')"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-gray-200 hover:border-gray-400 hover:bg-gray-50 transition text-left group"
                    data-value="">
                <i class="fas fa-th-large text-gray-400 w-5 text-center"></i>
                <span class="font-medium text-gray-700">Semua Status</span>
                <i class="fas fa-check text-gray-500 ml-auto hidden" id="statusCheckAll"></i>
            </button>
            <button type="button" onclick="selectPickerStatus('active','Aktif')"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-gray-200 hover:border-green-400 hover:bg-green-50 transition text-left group"
                    data-value="active">
                <i class="fas fa-circle text-green-500 w-5 text-center text-sm"></i>
                <span class="font-medium text-gray-800">Aktif</span>
                <i class="fas fa-check text-green-500 ml-auto hidden" id="statusCheckActive"></i>
            </button>
            <button type="button" onclick="selectPickerStatus('inactive','Nonaktif')"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-gray-200 hover:border-red-400 hover:bg-red-50 transition text-left group"
                    data-value="inactive">
                <i class="fas fa-circle text-red-400 w-5 text-center text-sm"></i>
                <span class="font-medium text-gray-800">Nonaktif</span>
                <i class="fas fa-check text-red-400 ml-auto hidden" id="statusCheckInactive"></i>
            </button>
        </div>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════
     PICKER MODAL: PERANGKAT DAERAH (Searchable)
════════════════════════════════════════════════════════════ --}}
<div id="modalPickerPd" class="fixed inset-0 z-[200] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/60" onclick="closePicker('pd')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 flex flex-col max-h-[85vh] overflow-hidden">

        {{-- Header --}}
        <div class="px-5 py-4 border-b flex items-center justify-between flex-shrink-0">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-building text-blue-600"></i> Pilih Perangkat Daerah
            </h3>
            <button onclick="closePicker('pd')" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Legend --}}
        <div id="pdPickerLegend" class="hidden px-4 pt-3 flex flex-wrap gap-2 text-xs flex-shrink-0">
            <span class="inline-flex items-center gap-1 bg-green-50 text-green-700 border border-green-200 px-2 py-1 rounded-full">
                <i class="fas fa-circle text-[8px]"></i> Tersedia
            </span>
            <span class="inline-flex items-center gap-1 bg-orange-50 text-orange-700 border border-orange-200 px-2 py-1 rounded-full">
                <i class="fas fa-user text-[8px]"></i> Sudah ada operator
            </span>
            <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 border border-blue-200 px-2 py-1 rounded-full">
                <i class="fas fa-star text-[8px]"></i> Akun yang sedang diedit
            </span>
        </div>

        {{-- Search --}}
        <div class="px-4 py-3 flex-shrink-0">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="pdPickerSearch" placeholder="Cari nama atau singkatan..."
                       oninput="renderPdList(this.value)"
                       class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        {{-- "Semua" option (filter only) --}}
        <div id="pdPickerAllOption" class="hidden px-4 pb-2 flex-shrink-0">
            <button type="button" onclick="selectPickerPdAll()"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border-2 border-dashed border-gray-300 hover:border-blue-400 hover:bg-blue-50 transition text-left">
                <i class="fas fa-globe text-gray-400 w-5 text-center"></i>
                <span class="font-medium text-gray-600">Semua Perangkat Daerah</span>
            </button>
        </div>

        {{-- List --}}
        <div id="pdPickerList" class="flex-1 overflow-y-auto divide-y divide-gray-100">
            {{-- Populated by JS --}}
        </div>

        {{-- Footer count --}}
        <div class="px-4 py-2.5 border-t bg-gray-50 flex-shrink-0">
            <p class="text-xs text-gray-500" id="pdPickerCount">Memuat data...</p>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Data dari PHP ──────────────────────────────────────────────────────
var pdData = @json($pdInfo);

// ── State ──────────────────────────────────────────────────────────────
var currentUserId   = null;
var currentResetId  = null;
var currentDeleteId = null;
var isEditMode      = false;

// Picker state
var pickerType       = null;  // 'role' | 'status' | 'pd'
var pickerContext     = null;  // 'filter' | 'form'
var pickerEditUserId = null;  // when editing: the user's ID (for PD ownership detection)

// ── Modal helpers ──────────────────────────────────────────────────────
function openModal(id) {
    var el = document.getElementById(id);
    el.classList.remove('hidden');
    el.classList.add('flex');
}
function closeModal(id) {
    var el = document.getElementById(id);
    el.classList.add('hidden');
    el.classList.remove('flex');
}

// ── Password toggle ────────────────────────────────────────────────────
function togglePwd(inputId, iconId) {
    var inp  = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// ── Form helpers ───────────────────────────────────────────────────────
function showFormError(msg) {
    var el = document.getElementById('formError');
    el.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i>' + msg;
    el.classList.remove('hidden');
}
function clearFormError() { document.getElementById('formError').classList.add('hidden'); }
function notify(msg, type) { showToast(msg, type || 'success'); }

// ═══════════════════════════════════════════════════════════════════════
// PICKER: OPEN / CLOSE
// ═══════════════════════════════════════════════════════════════════════

function openPicker(type, context) {
    pickerType    = type;
    pickerContext = context;

    if (type === 'role') {
        // Show/hide "Semua" option
        document.getElementById('rolePickerAllOption').style.display = context === 'filter' ? '' : 'none';
        // Highlight current value
        var cur = context === 'filter'
            ? document.getElementById('filterRole').value
            : document.getElementById('fieldRole').value;
        updateRoleHighlight(cur);
        openModal('modalPickerRole');

    } else if (type === 'status') {
        var cur = document.getElementById('filterStatus').value;
        updateStatusHighlight(cur);
        openModal('modalPickerStatus');

    } else if (type === 'pd') {
        pickerEditUserId = (context === 'form' && isEditMode) ? currentUserId : null;
        // Show legend only in form mode
        document.getElementById('pdPickerLegend').style.display = context === 'form' ? 'flex' : 'none';
        // Show "Semua" only in filter mode
        document.getElementById('pdPickerAllOption').style.display = context === 'filter' ? '' : 'none';
        // Clear search
        document.getElementById('pdPickerSearch').value = '';
        renderPdList('');
        openModal('modalPickerPd');
        setTimeout(function() { document.getElementById('pdPickerSearch').focus(); }, 150);
    }
}

function closePicker(type) {
    var map = { role: 'modalPickerRole', status: 'modalPickerStatus', pd: 'modalPickerPd' };
    closeModal(map[type]);
}

// ═══════════════════════════════════════════════════════════════════════
// PICKER: ROLE
// ═══════════════════════════════════════════════════════════════════════

function updateRoleHighlight(val) {
    document.getElementById('roleCheckDaerah').classList.toggle('hidden', val !== 'daerah');
    document.getElementById('roleCheckMaster').classList.toggle('hidden', val !== 'master');
}

function selectPickerRole(value, label) {
    if (pickerContext === 'filter') {
        document.getElementById('filterRole').value = value;
        document.getElementById('filterRoleBtnLabel').textContent = label;

    } else {
        document.getElementById('fieldRole').value = value;
        var iconEl  = document.getElementById('fieldRoleBtnIcon');
        var lblEl   = document.getElementById('fieldRoleBtnLabel');
        var descEl  = document.getElementById('fieldRoleBtnDesc');

        if (value === 'daerah') {
            iconEl.className  = 'w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0';
            iconEl.innerHTML  = '<i class="fas fa-user text-blue-600"></i>';
            lblEl.textContent = 'Operator Daerah';
            lblEl.className   = 'text-sm font-medium text-gray-800';
            descEl.textContent= 'Input laporan untuk perangkat daerah sendiri';
            descEl.className  = 'text-xs text-gray-500';
            descEl.classList.remove('hidden');
        } else if (value === 'master') {
            iconEl.className  = 'w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0';
            iconEl.innerHTML  = '<i class="fas fa-shield-alt text-yellow-600"></i>';
            lblEl.textContent = 'Admin Master';
            lblEl.className   = 'text-sm font-medium text-gray-800';
            descEl.textContent= 'Akses penuh ke seluruh data dan administrasi';
            descEl.className  = 'text-xs text-gray-500';
            descEl.classList.remove('hidden');
        }
        handleRoleChange();
    }
    closePicker('role');
}

function handleRoleChange() {
    var role = document.getElementById('fieldRole').value;
    var wPd  = document.getElementById('wrapPd');
    wPd.style.display = (role === 'master') ? 'none' : 'block';
    if (role === 'master') {
        document.getElementById('fieldPd').value = '';
        resetPdBtn();
        document.getElementById('pdWarning').classList.add('hidden');
    }
}

// ═══════════════════════════════════════════════════════════════════════
// PICKER: STATUS
// ═══════════════════════════════════════════════════════════════════════

function updateStatusHighlight(val) {
    document.getElementById('statusCheckAll').classList.toggle('hidden',     val !== '');
    document.getElementById('statusCheckActive').classList.toggle('hidden',  val !== 'active');
    document.getElementById('statusCheckInactive').classList.toggle('hidden',val !== 'inactive');
}

function selectPickerStatus(value, label) {
    document.getElementById('filterStatus').value = value;
    document.getElementById('filterStatusBtnLabel').textContent = label;
    closePicker('status');
}

// ═══════════════════════════════════════════════════════════════════════
// PICKER: PERANGKAT DAERAH
// ═══════════════════════════════════════════════════════════════════════

function renderPdList(search) {
    var container = document.getElementById('pdPickerList');
    var q = (search || '').trim().toLowerCase();

    // Current selection
    var curId = pickerContext === 'filter'
        ? document.getElementById('filterPd').value
        : document.getElementById('fieldPd').value;

    var filtered = pdData.filter(function(pd) {
        if (!q) return true;
        return pd.nama.toLowerCase().indexOf(q) !== -1
            || (pd.singkatan && pd.singkatan.toLowerCase().indexOf(q) !== -1)
            || (pd.kode && pd.kode.toLowerCase().indexOf(q) !== -1);
    });

    document.getElementById('pdPickerCount').textContent =
        filtered.length + ' perangkat daerah' + (q ? ' ditemukan' : '');

    if (filtered.length === 0) {
        container.innerHTML =
            '<div class="py-12 text-center text-gray-400">' +
            '<i class="fas fa-search text-3xl mb-2 block"></i>' +
            '<p class="text-sm">Tidak ada hasil untuk "<strong>' + escHtml(search) + '</strong>"</p>' +
            '</div>';
        return;
    }

    var html = '';
    filtered.forEach(function(pd) {
        var isSelected = (String(pd.id) === String(curId));
        // Is this PD owned by the user currently being edited?
        var isOwnedByEditUser = pickerEditUserId && pd.operator_id === pickerEditUserId;
        var hasOtherOp = pd.has_operator && !isOwnedByEditUser;

        // Badge
        var badge = '';
        if (isOwnedByEditUser) {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full flex-shrink-0">' +
                    '<i class="fas fa-star text-[9px]"></i> Akun ini</span>';
        } else if (hasOtherOp) {
            var opStatus = pd.operator_active
                ? '<i class="fas fa-circle text-green-500 text-[8px]"></i>'
                : '<i class="fas fa-circle text-gray-400 text-[8px]"></i>';
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-orange-50 text-orange-700 border border-orange-200 px-2 py-0.5 rounded-full flex-shrink-0">' +
                    opStatus + ' ' + escHtml(pd.operator_name) + '</span>';
        } else {
            badge = '<span class="inline-flex items-center gap-1 text-xs bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-full flex-shrink-0">' +
                    '<i class="fas fa-circle text-[8px]"></i> Tersedia</span>';
        }

        var rowClass = 'flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 transition';
        if (isSelected) rowClass += ' bg-blue-50 border-l-4 border-blue-500';

        html += '<div onclick="selectPickerPd(' + pd.id + ')" class="' + rowClass + '">' +
                '<div class="min-w-0 flex-1 pr-3">' +
                '<p class="font-medium text-sm text-gray-800 truncate">' + escHtml(pd.nama) + '</p>' +
                '<p class="text-xs text-gray-500">' +
                    (pd.singkatan ? escHtml(pd.singkatan) + ' · ' : '') + escHtml(pd.jenis_label || pd.jenis) +
                '</p>' +
                '</div>' +
                '<div class="flex items-center gap-1.5 flex-shrink-0">' +
                badge +
                (isSelected ? '<i class="fas fa-check-circle text-blue-500 text-base ml-1"></i>' : '') +
                '</div>' +
                '</div>';
    });

    container.innerHTML = html;
}

function selectPickerPdAll() {
    document.getElementById('filterPd').value = '';
    document.getElementById('filterPdBtnLabel').textContent = 'Semua Perangkat';
    closePicker('pd');
}

function selectPickerPd(id) {
    var pd = pdData.find(function(p) { return p.id === id; });
    if (!pd) return;

    if (pickerContext === 'filter') {
        document.getElementById('filterPd').value = id;
        document.getElementById('filterPdBtnLabel').textContent = pd.singkatan || pd.nama;

    } else {
        document.getElementById('fieldPd').value = id;

        // Update button display
        var lblEl = document.getElementById('fieldPdBtnLabel');
        var subEl = document.getElementById('fieldPdBtnSub');
        lblEl.textContent = pd.nama;
        lblEl.className   = 'text-sm font-medium text-gray-800 truncate';
        subEl.textContent = (pd.singkatan ? pd.singkatan + ' · ' : '') + (pd.jenis_label || pd.jenis);
        subEl.classList.remove('hidden');

        // PD occupancy warning (only in create mode)
        var isOwned = pickerEditUserId && pd.operator_id === pickerEditUserId;
        if (!isOwned && pd.has_operator) {
            var status = pd.operator_active ? '(aktif)' : '(nonaktif)';
            document.getElementById('pdWarningText').innerHTML =
                'Perangkat daerah ini sudah memiliki operator: <strong>' + escHtml(pd.operator_name) + '</strong> ' + status +
                '. Tetap bisa ditambahkan, namun pertimbangkan apakah operator lama perlu dinonaktifkan.';
            document.getElementById('pdWarning').classList.remove('hidden');
        } else {
            document.getElementById('pdWarning').classList.add('hidden');
        }
    }
    closePicker('pd');
}

function resetPdBtn() {
    document.getElementById('fieldPdBtnLabel').textContent = 'Pilih perangkat daerah...';
    document.getElementById('fieldPdBtnLabel').className   = 'text-sm text-gray-500';
    document.getElementById('fieldPdBtnSub').classList.add('hidden');
}

function resetRoleBtn() {
    var iconEl = document.getElementById('fieldRoleBtnIcon');
    iconEl.className = 'w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0';
    iconEl.innerHTML = '<i class="fas fa-user-tag text-gray-400"></i>';
    document.getElementById('fieldRoleBtnLabel').textContent = 'Pilih role pengguna...';
    document.getElementById('fieldRoleBtnLabel').className   = 'text-sm text-gray-500';
    document.getElementById('fieldRoleBtnDesc').classList.add('hidden');
}

function setRoleBtnDisplay(value) {
    var iconEl = document.getElementById('fieldRoleBtnIcon');
    var lblEl  = document.getElementById('fieldRoleBtnLabel');
    var descEl = document.getElementById('fieldRoleBtnDesc');
    if (value === 'daerah') {
        iconEl.className  = 'w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0';
        iconEl.innerHTML  = '<i class="fas fa-user text-blue-600"></i>';
        lblEl.textContent = 'Operator Daerah';
        lblEl.className   = 'text-sm font-medium text-gray-800';
        descEl.textContent= 'Input laporan untuk perangkat daerah sendiri';
        descEl.className  = 'text-xs text-gray-500';
        descEl.classList.remove('hidden');
    } else if (value === 'master') {
        iconEl.className  = 'w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0';
        iconEl.innerHTML  = '<i class="fas fa-shield-alt text-yellow-600"></i>';
        lblEl.textContent = 'Admin Master';
        lblEl.className   = 'text-sm font-medium text-gray-800';
        descEl.textContent= 'Akses penuh ke seluruh data dan administrasi';
        descEl.className  = 'text-xs text-gray-500';
        descEl.classList.remove('hidden');
    } else {
        resetRoleBtn();
    }
}

// ── HTML escape helpers ────────────────────────────────────────────────
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ═══════════════════════════════════════════════════════════════════════
// CREATE / EDIT MODAL
// ═══════════════════════════════════════════════════════════════════════

function openCreateModal() {
    isEditMode       = false;
    currentUserId    = null;
    pickerEditUserId = null;

    document.getElementById('modalUserTitle').textContent  = 'Tambah Pengguna';
    document.getElementById('btnSubmitLabel').textContent  = 'Simpan';
    document.getElementById('formUser').reset();
    document.getElementById('fieldActive').checked         = true;
    document.getElementById('passRequired').style.display  = '';
    document.getElementById('passHint').classList.add('hidden');
    document.getElementById('fieldPassword').required      = true;
    document.getElementById('wrapPd').style.display        = 'block';
    document.getElementById('pdWarning').classList.add('hidden');
    document.getElementById('fieldRole').value             = '';
    document.getElementById('fieldPd').value               = '';
    resetRoleBtn();
    resetPdBtn();
    clearFormError();
    openModal('modalUser');
}

function openEditModal(id) {
    isEditMode    = true;
    currentUserId = id;
    clearFormError();
    showLoading();

    $.get('/admin/users/' + id)
        .done(function(res) {
            hideLoading();
            var u = res.user;
            pickerEditUserId = id;

            document.getElementById('modalUserTitle').textContent  = 'Edit Pengguna';
            document.getElementById('btnSubmitLabel').textContent  = 'Simpan Perubahan';
            document.getElementById('fieldName').value             = u.name;
            document.getElementById('fieldEmail').value            = u.email;
            document.getElementById('fieldActive').checked         = !!u.is_active;
            document.getElementById('fieldPassword').value         = '';
            document.getElementById('fieldPassword').required      = false;
            document.getElementById('passRequired').style.display  = 'none';
            document.getElementById('passHint').classList.remove('hidden');
            document.getElementById('pdWarning').classList.add('hidden');

            // Set role (set UI directly without opening picker)
            document.getElementById('fieldRole').value = u.role;
            setRoleBtnDisplay(u.role);

            // Set PD
            if (u.perangkat_daerah_id) {
                document.getElementById('fieldPd').value = u.perangkat_daerah_id;
                var pd = pdData.find(function(p) { return p.id === u.perangkat_daerah_id; });
                if (pd) {
                    var lblEl = document.getElementById('fieldPdBtnLabel');
                    var subEl = document.getElementById('fieldPdBtnSub');
                    lblEl.textContent = pd.nama;
                    lblEl.className   = 'text-sm font-medium text-gray-800 truncate';
                    subEl.textContent = (pd.singkatan ? pd.singkatan + ' · ' : '') + (pd.jenis_label || pd.jenis);
                    subEl.classList.remove('hidden');
                }
            } else {
                document.getElementById('fieldPd').value = '';
                resetPdBtn();
            }

            handleRoleChange();
            openModal('modalUser');
        })
        .fail(function() { hideLoading(); notify('Gagal memuat data pengguna.', 'error'); });
}

// ── Submit ──────────────────────────────────────────────────────────────
document.getElementById('formUser').addEventListener('submit', function(e) {
    e.preventDefault();
    clearFormError();

    var role = document.getElementById('fieldRole').value;
    var pd   = document.getElementById('fieldPd').value;

    if (!role) { showFormError('Role wajib dipilih.'); return; }
    if (role === 'daerah' && !pd) { showFormError('Perangkat Daerah wajib dipilih untuk Operator Daerah.'); return; }

    var data = {
        _token:              '{{ csrf_token() }}',
        name:                document.getElementById('fieldName').value,
        email:               document.getElementById('fieldEmail').value,
        password:            document.getElementById('fieldPassword').value,
        role:                role,
        perangkat_daerah_id: pd || null,
        is_active:           document.getElementById('fieldActive').checked ? 1 : 0,
    };

    var url    = isEditMode ? '/admin/users/' + currentUserId : '/admin/users';
    var method = isEditMode ? 'PUT' : 'POST';

    showLoading();
    $.ajax({ url: url, method: method, data: data })
        .done(function(res) {
            hideLoading();
            closeModal('modalUser');
            notify(res.message);
            setTimeout(function() { location.reload(); }, 800);
        })
        .fail(function(xhr) {
            hideLoading();
            var errs = xhr.responseJSON && xhr.responseJSON.errors;
            var msg  = errs
                ? Object.values(errs).flat().join('<br>')
                : ((xhr.responseJSON && xhr.responseJSON.message) || 'Terjadi kesalahan.');
            showFormError(msg);
        });
});

// ── Toggle Active ───────────────────────────────────────────────────────
function toggleActive(id, name) {
    if (id === {{ auth()->id() }}) return;
    showLoading();
    $.ajax({ url: '/admin/users/' + id + '/toggle-active', method: 'PATCH',
             data: { _token: '{{ csrf_token() }}' } })
        .done(function(res) {
            hideLoading();
            var isActive = res.is_active;
            var btn = document.getElementById('toggle-' + id);
            if (btn) { btn.classList.toggle('bg-green-500', isActive); btn.classList.toggle('bg-gray-300', !isActive); }
            var dot = document.getElementById('toggle-dot-' + id);
            if (dot) { dot.classList.toggle('translate-x-6', isActive); dot.classList.toggle('translate-x-1', !isActive); }
            var lbl = document.getElementById('toggle-label-' + id);
            if (lbl) { lbl.textContent = isActive ? 'Aktif' : 'Nonaktif'; lbl.className = 'text-xs mt-0.5 ' + (isActive ? 'text-green-600' : 'text-gray-400'); }
            var row = document.getElementById('row-' + id);
            if (row) row.classList.toggle('opacity-60', !isActive);

            // Refresh pdData so picker reflects new state
            if (!isActive) {
                pdData.forEach(function(pd) { if (pd.operator_id === id) pd.operator_active = false; });
            } else {
                pdData.forEach(function(pd) { if (pd.operator_id === id) pd.operator_active = true; });
            }
            notify(res.message);
        })
        .fail(function(xhr) { hideLoading(); notify((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal mengubah status.', 'error'); });
}

// ── Reset Password ──────────────────────────────────────────────────────
function openResetModal(id, name) {
    currentResetId = id;
    document.getElementById('resetUserName').textContent = name;
    document.getElementById('newPassword').value = '';
    document.getElementById('resetError').classList.add('hidden');
    openModal('modalReset');
}

function submitReset() {
    var pwd = document.getElementById('newPassword').value.trim();
    if (!pwd) {
        document.getElementById('resetError').innerHTML = 'Password baru wajib diisi.';
        document.getElementById('resetError').classList.remove('hidden');
        return;
    }
    showLoading();
    $.ajax({ url: '/admin/users/' + currentResetId + '/reset-password', method: 'PATCH',
             data: { _token: '{{ csrf_token() }}', new_password: pwd } })
        .done(function(res) { hideLoading(); closeModal('modalReset'); notify(res.message); })
        .fail(function(xhr) {
            hideLoading();
            var err = (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.new_password && xhr.responseJSON.errors.new_password[0])
                || (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal mereset password.';
            document.getElementById('resetError').innerHTML = err;
            document.getElementById('resetError').classList.remove('hidden');
        });
}

// ── Delete ──────────────────────────────────────────────────────────────
function deleteUser(id, name) {
    currentDeleteId = id;
    document.getElementById('deleteUserName').textContent = name;
    openModal('modalDelete');
}

function confirmDelete() {
    showLoading();
    $.ajax({ url: '/admin/users/' + currentDeleteId, method: 'DELETE',
             data: { _token: '{{ csrf_token() }}' } })
        .done(function(res) {
            hideLoading();
            closeModal('modalDelete');
            notify(res.message);
            setTimeout(function() { location.reload(); }, 800);
        })
        .fail(function(xhr) {
            hideLoading();
            closeModal('modalDelete');
            notify((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menghapus pengguna.', 'error');
        });
}
</script>
@endpush
