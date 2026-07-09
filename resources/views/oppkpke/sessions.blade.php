@extends('layouts.oppkpke')

@section('title', 'Sesi Login Aktif')
@section('page-title', 'Sesi Login Aktif')
@section('page-subtitle', 'Pantau akun yang sedang login & logout paksa bila perlu')

@section('content')

{{-- ── Info banner ─────────────────────────────────────────────── --}}
<div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
    <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div class="text-xs md:text-sm text-blue-800 space-y-1">
        <p>Daftar akun yang <strong>sedang login</strong> (aktif dalam {{ (int) config('session.lifetime', 120) }} menit terakhir). Gunakan <strong>Logout Paksa</strong> untuk menghentikan sesi akun mana pun — mis. dinas yang lupa logout atau memakai akun bersama.</p>
        <p class="text-blue-600">Setelah di-logout paksa, akun tersebut harus login ulang untuk mengakses sistem.</p>
    </div>
</div>

{{-- ── Ringkasan ───────────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-3 md:gap-4 mb-5">
    <div class="bg-white rounded-xl border shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-gray-800">{{ $sessions->count() }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Akun Login</p>
    </div>
    <div class="bg-blue-50 rounded-xl border border-blue-200 shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-blue-700">{{ $sessions->where('role', 'daerah')->count() }}</p>
        <p class="text-xs text-blue-600 mt-0.5">Operator Daerah</p>
    </div>
    <div class="bg-gray-50 rounded-xl border shadow-sm p-4 text-center">
        <p class="text-2xl font-bold text-gray-700">{{ $sessions->sum('session_count') }}</p>
        <p class="text-xs text-gray-500 mt-0.5">Total Sesi/Perangkat</p>
    </div>
</div>

{{-- ── Tabel sesi ──────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border shadow-sm overflow-hidden">
    <div class="px-4 md:px-6 py-3 border-b bg-gray-50 flex items-center justify-between">
        <span class="text-xs md:text-sm text-gray-600 font-medium">Akun yang sedang login</span>
        <button onclick="location.reload()" class="text-xs text-blue-600 hover:underline flex items-center gap-1">
            <i class="fas fa-rotate-right"></i> Muat ulang
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left">Akun</th>
                    <th class="px-5 py-3 text-left">Role</th>
                    <th class="px-5 py-3 text-left">Perangkat Daerah</th>
                    <th class="px-5 py-3 text-center">Sesi</th>
                    <th class="px-5 py-3 text-left">IP Terakhir</th>
                    <th class="px-5 py-3 text-left">Aktivitas</th>
                    <th class="px-5 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($sessions as $s)
                <tr id="session-row-{{ $s['user_id'] }}" class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-gray-800">
                            {{ $s['name'] }}
                            @if($s['is_self'])
                                <span class="ml-1 text-xs bg-blue-100 text-blue-600 px-1.5 py-0.5 rounded">Anda</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $s['email'] }}</p>
                    </td>
                    <td class="px-5 py-3.5">
                        @php
                            $roleBadge = [
                                'master'  => 'bg-yellow-100 text-yellow-800',
                                'daerah'  => 'bg-blue-100 text-blue-800',
                                'it_team' => 'bg-teal-100 text-teal-800',
                            ][$s['role']] ?? 'bg-gray-100 text-gray-700';
                        @endphp
                        <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full {{ $roleBadge }}">
                            {{ $s['role_label'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-gray-700 text-sm">
                        {{ $s['perangkat'] ?? '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="inline-flex items-center justify-center min-w-[24px] h-6 px-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-full">
                            {{ $s['session_count'] }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500 font-mono">{{ $s['ip_address'] ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">{{ $s['last_activity'] }}</td>
                    <td class="px-5 py-3.5 text-center">
                        @if($s['is_self'])
                            <span class="text-xs text-gray-400 italic">—</span>
                        @else
                            <button type="button"
                                    onclick="forceLogout({{ $s['user_id'] }}, @js($s['name']))"
                                    class="inline-flex items-center gap-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                <i class="fas fa-right-from-bracket"></i> Logout Paksa
                            </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                        <i class="fas fa-user-slash text-4xl mb-3 block"></i>
                        <p class="font-medium">Tidak ada akun yang sedang login</p>
                        <p class="text-sm mt-1">Semua sesi sudah berakhir atau belum ada yang login.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Modal konfirmasi ────────────────────────────────────────── --}}
<div id="modalForceLogout" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalForceLogout')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 p-5 md:p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-right-from-bracket text-red-500 text-2xl"></i>
        </div>
        <h3 class="font-semibold text-gray-800 text-base mb-1">Logout Paksa Akun?</h3>
        <p class="text-sm text-gray-500 mb-1">Akun <strong id="flTargetName" class="text-gray-700"></strong> akan dikeluarkan dari semua perangkat.</p>
        <p class="text-xs text-gray-400 mb-5">Akun tersebut harus login ulang untuk mengakses sistem.</p>
        <div class="flex gap-2 md:gap-3">
            <button onclick="closeModal('modalForceLogout')"
                    class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
            <button id="flConfirmBtn" onclick="confirmForceLogout()"
                    class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-sm font-semibold transition">
                Ya, Logout Paksa
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
var flTargetId = null;

function openModal(id)  { var el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { var el = document.getElementById(id); el.classList.add('hidden');  el.classList.remove('flex'); }

function forceLogout(userId, name) {
    flTargetId = userId;
    document.getElementById('flTargetName').textContent = name;
    openModal('modalForceLogout');
}

function confirmForceLogout() {
    if (!flTargetId) return;
    var btn = document.getElementById('flConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

    $.ajax({
        url:    '{{ route('admin.sessions.force-logout') }}',
        method: 'POST',
        data:   { user_id: flTargetId },
    }).done(function (res) {
        closeModal('modalForceLogout');
        showToast(res.message, 'success');
        var row = document.getElementById('session-row-' + flTargetId);
        if (row) row.remove();
        flTargetId = null;
    }).fail(function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal logout paksa. Coba lagi.';
        showToast(msg, 'error');
    }).always(function () {
        btn.disabled = false;
        btn.innerHTML = 'Ya, Logout Paksa';
    });
}
</script>
@endpush
