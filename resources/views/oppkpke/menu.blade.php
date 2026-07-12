{{-- resources/views/oppkpke/menu.blade.php
     Halaman Menu (terutama untuk mobile) — menggantikan sidebar geser.
     Tampilan tenang & kontras tinggi untuk pengguna lanjut usia. --}}
@extends('layouts.oppkpke')

@section('title', 'Menu')
@section('page-title', 'Menu')
@section('page-subtitle', 'Semua fitur aplikasi')

@php
    $u    = auth()->user();
    $mm   = app(\App\Services\MenuManager::class);
    $role = $u->isMaster() ? 'master' : ($u->isDaerah() ? 'daerah' : 'it_team');

    // Item tampil bila: tak punya key (selalu) ATAU role bukan yang dikelola
    // ATAU diaktifkan Tim IT di Kelola Menu.
    $show = function ($key) use ($mm, $role) {
        if ($key === null) return true;
        if (! in_array($role, ['master', 'daerah'], true)) return true;
        return $mm->isEnabled($role, $key);
    };

    // Definisi grup per role: [judul, [ [key, label, ikon, route, params, activePattern], ... ]]
    if ($role === 'daerah') {
        $groups = [
            ['Utama', [
                ['dashboard',       'Beranda',          'fa-house',         'oppkpke.dashboard',      [], 'oppkpke.dashboard'],
                ['laporan',         'Input Data',       'fa-pen-to-square', 'oppkpke.laporan.index',  [], 'oppkpke.laporan.*'],
                ['report',          'Rekap Laporan',    'fa-table-list',    'oppkpke.report',         [], 'oppkpke.report'],
                ['chat',            'Chat Support IT',  'fa-comments',      'oppkpke.chat.index',     [], 'oppkpke.chat.*'],
            ]],
            ['Bantuan & Akun', [
                ['panduan',         'Panduan',          'fa-book-open',     'oppkpke.panduan',        [], 'oppkpke.panduan'],
                ['pic',             'Identitas PIC',    'fa-id-card',       'oppkpke.pic.form',       [], 'oppkpke.pic.*'],
                ['change_password', 'Ganti Password',   'fa-key',           'oppkpke.profile.change-password', [], 'oppkpke.profile.change-password'],
            ]],
        ];
    } elseif ($role === 'it_team') {
        $groups = [
            ['Utama', [
                [null, 'Ikhtisar Eksekutif', 'fa-chart-line', 'oppkpke.presentasi',          [], 'oppkpke.presentasi'],
                [null, 'Inbox Support',      'fa-comments',   'oppkpke.chat.index',          [], 'oppkpke.chat.*'],
                [null, 'Pengumuman',         'fa-bullhorn',   'oppkpke.announcements.index', [], 'oppkpke.announcements.*'],
            ]],
            ['Administrasi', [
                [null, 'Perangkat Daerah',   'fa-sitemap',        'admin.perangkat-daerah.index', [], 'admin.perangkat-daerah.*'],
                [null, 'Sesi Login',         'fa-user-clock',     'admin.sessions.index',         [], 'admin.sessions.*'],
                [null, 'Audit Log',          'fa-clipboard-list', 'admin.audit.index',            [], 'admin.audit.*'],
                [null, 'Kelola Menu',        'fa-sliders',        'admin.menu-settings.index',    [], 'admin.menu-settings.*'],
                [null, 'Kelola Strategi',    'fa-diagram-project','admin.strategi.index',         [], 'admin.strategi.*'],
            ]],
            ['Akun', [
                [null, 'Ganti Password',     'fa-key', 'oppkpke.profile.change-password', [], 'oppkpke.profile.change-password'],
            ]],
        ];
    } else { // master
        $groups = [
            ['Utama', [
                ['dashboard',  'Dashboard',           'fa-gauge',         'oppkpke.dashboard',  [], 'oppkpke.dashboard'],
                ['presentasi', 'Ikhtisar Eksekutif',  'fa-chart-line',    'oppkpke.presentasi', [], 'oppkpke.presentasi'],
                ['laporan',    'Input Laporan',       'fa-pen-to-square', 'oppkpke.laporan.index', [], 'oppkpke.laporan.*'],
                ['statistik',  'Statistik',           'fa-chart-column',  'oppkpke.statistik',  [], 'oppkpke.statistik'],
                ['matrix',     'Matriks',             'fa-table',         'oppkpke.matrix', ['tahun' => request('tahun', date('Y'))], 'oppkpke.matrix'],
            ]],
            ['Data & Impor', [
                ['import',          'Import OPPKPKE',  'fa-file-import',    'oppkpke.import',          [], 'oppkpke.import'],
                ['import_rat',      'Import RAT',      'fa-file-arrow-up',  'oppkpke.import.rat',      [], 'oppkpke.import.rat*'],
                ['import_hierarki', 'Import Hierarki', 'fa-sitemap',        'oppkpke.import.hierarki', [], 'oppkpke.import.hierarki*'],
            ]],
            ['Administrasi', [
                ['users',         'Kelola Pengguna', 'fa-users-gear',     'admin.users.index',          [], 'admin.users.*'],
                ['announcements', 'Pengumuman',      'fa-bullhorn',       'oppkpke.announcements.index',[], 'oppkpke.announcements.*'],
                ['chat',          'Pantau Chat',     'fa-comments',       'oppkpke.chat.index',         [], 'oppkpke.chat.*'],
                ['sessions',      'Sesi Login',      'fa-user-clock',     'admin.sessions.index',       [], 'admin.sessions.*'],
                [null,            'Kelola Strategi', 'fa-diagram-project','admin.strategi.index',       [], 'admin.strategi.*'],
            ]],
            ['Bantuan & Akun', [
                ['panduan', 'Panduan Penggunaan', 'fa-book-open', 'oppkpke.panduan',                 [], 'oppkpke.panduan'],
                [null,      'Ganti Password',     'fa-key',       'oppkpke.profile.change-password', [], 'oppkpke.profile.change-password'],
            ]],
        ];
    }
@endphp

@section('content')
<div class="max-w-xl mx-auto space-y-5">

    {{-- Profil ringkas --}}
    <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl p-4">
        <div class="w-12 h-12 rounded-full bg-slate-700 text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
            {{ strtoupper(substr($u->name ?? 'U', 0, 1)) }}
        </div>
        <div class="min-w-0">
            <p class="font-semibold text-gray-800 truncate">{{ $u->name ?? 'Pengguna' }}</p>
            <p class="text-xs text-gray-500">{{ $mm->roleLabel($role) ?? ucfirst($role) }}</p>
        </div>
    </div>

    @foreach($groups as [$judul, $items])
        @php $visible = array_values(array_filter($items, fn ($it) => $show($it[0]))); @endphp
        @if(count($visible))
            <div>
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider px-1 mb-1.5">{{ $judul }}</p>
                <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
                    @foreach($visible as $it)
                        @php
                            [$key, $label, $icon, $routeName, $params, $active] = $it;
                            $isActive = request()->routeIs($active);
                        @endphp
                        <a href="{{ route($routeName, $params) }}"
                           class="flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 active:bg-gray-100 transition {{ $isActive ? 'bg-slate-50' : '' }}">
                            <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center flex-shrink-0">
                                <i class="fas {{ $icon }}"></i>
                            </span>
                            <span class="text-[15px] text-gray-800 flex-1 min-w-0 truncate">{{ $label }}</span>
                            @if($isActive)
                                <span class="text-[10px] text-slate-500 font-medium">aktif</span>
                            @endif
                            <i class="fas fa-chevron-right text-gray-300 text-xs flex-shrink-0"></i>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    {{-- Export (master, digabung agar tak memenuhi daftar) --}}
    @if($role === 'master' && $show('export'))
        <div>
            <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider px-1 mb-1.5">Export</p>
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('oppkpke.export.excel', request()->query()) }}"
                   class="flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl py-3 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-file-excel text-emerald-700"></i> Excel
                </a>
                <a href="{{ route('oppkpke.export.pdf', request()->query()) }}"
                   class="flex items-center justify-center gap-2 bg-white border border-gray-200 rounded-xl py-3 text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-file-pdf text-red-700"></i> PDF
                </a>
            </div>
        </div>
    @endif

    {{-- Keluar --}}
    <form method="POST" action="{{ route('logout') }}" class="pt-1">
        @csrf
        <button type="submit"
                class="w-full flex items-center justify-center gap-2 bg-white border border-red-200 text-red-600 hover:bg-red-50 rounded-xl py-3.5 text-sm font-medium transition">
            <i class="fas fa-arrow-right-from-bracket"></i> Keluar
        </button>
    </form>

    <p class="text-center text-[11px] text-gray-400 pb-2">OPPKPKE — Sistem Pengentasan Kemiskinan</p>
</div>
@endsection
