{{-- resources/views/layouts/oppkpke.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'OPPKPKE') - Sistem Pengentasan Kemiskinan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        [x-cloak]  { display: none !important; }
        .loader    { border-top-color: #475569; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .nav-active { background-color: rgba(255,255,255,0.12); border-right: 4px solid #cbd5e1; }

        /* Smooth sidebar on mobile */
        #main-sidebar { will-change: transform; }

        /* ── Skeleton loader (shimmer) ─────────────────────────────── */
        .skeleton {
            position: relative; overflow: hidden;
            background: #e8ecf1; border-radius: 0.5rem;
        }
        .skeleton::after {
            content: ''; position: absolute; inset: 0;
            transform: translateX(-100%);
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.65), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer { 100% { transform: translateX(100%); } }
        @media (prefers-reduced-motion: reduce) { .skeleton::after { animation: none; } }

        /* ── Bottom navigation (mobile) ────────────────────────────── */
        #bottom-nav {
            padding-bottom: env(safe-area-inset-bottom, 0px);
            box-shadow: 0 -1px 8px rgba(15, 23, 42, 0.06);
        }
        #bottom-nav a, #bottom-nav button {
            -webkit-tap-highlight-color: transparent;
        }
        /* Ruang bawah agar konten tak tertutup bottom-nav di mobile */
        @media (max-width: 1023px) {
            body.has-bottom-nav { padding-bottom: calc(4rem + env(safe-area-inset-bottom, 0px)); }
        }

        /* Palet natural: fokus keterbacaan untuk pengguna lanjut usia */
        :root {
            --c-ink: #1f2937;       /* teks utama */
            --c-muted: #64748b;     /* teks sekunder */
            --c-line: #e5e7eb;      /* garis */
        }

        /* Select2 full-width override */
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { height: 38px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; padding-left: 10px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }

        /* Mobile filter panel slide */
        .filter-panel-mobile { transition: max-height 0.3s ease, opacity 0.3s ease; }
        .filter-panel-mobile.collapsed { max-height: 0; overflow: hidden; opacity: 0; }
        .filter-panel-mobile.expanded  { max-height: 9999px; opacity: 1; }
    </style>
    @stack('styles')
</head>
<body class="bg-gray-50 min-h-screen has-bottom-nav">

    {{-- ── Backdrop (mobile sidebar) ──────────────────────────────── --}}
    <div id="sidebar-backdrop"
         class="fixed inset-0 bg-black/50 z-40 hidden"
         onclick="closeSidebar()"
         aria-hidden="true"></div>

    {{-- ── Sidebar ─────────────────────────────────────────────────── --}}
    <aside id="main-sidebar"
           class="fixed left-0 top-0 h-full w-64 bg-gradient-to-b from-blue-900 to-blue-800 text-white shadow-xl z-50 hidden lg:flex flex-col"
           role="navigation"
           aria-label="Menu utama">

        {{-- Brand + Close button (mobile) --}}
        <div class="p-4 border-b border-blue-700 flex items-center justify-between gap-2">
            <div class="min-w-0">
                <h1 class="text-lg font-bold flex items-center gap-2 truncate">
                    <i class="fas fa-hand-holding-heart text-yellow-400 flex-shrink-0"></i>
                    OPPKPKE
                </h1>
                <p class="text-blue-200 text-xs mt-0.5 truncate">Sistem Pengentasan Kemiskinan</p>
            </div>
            <button onclick="closeSidebar()"
                    class="lg:hidden flex-shrink-0 p-1.5 rounded-lg text-blue-300 hover:text-white hover:bg-white/10 transition"
                    aria-label="Tutup menu">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        {{-- User info --}}
        <div class="px-4 py-3 border-b border-blue-700 bg-blue-950/30">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-yellow-400 rounded-full flex items-center justify-center text-blue-900 font-bold text-sm flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name ?? 'User' }}</p>
                    @if(auth()->user()->isMaster())
                        <span class="inline-flex items-center gap-1 text-xs bg-yellow-400/20 text-yellow-300 px-2 py-0.5 rounded-full">
                            <i class="fas fa-shield-alt text-[10px]"></i> Admin Master
                        </span>
                    @elseif(auth()->user()->isItTeam())
                        <span class="inline-flex items-center gap-1 text-xs bg-purple-400/20 text-purple-200 px-2 py-0.5 rounded-full">
                            <i class="fas fa-headset text-[10px]"></i> Tim IT
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs bg-green-400/20 text-green-300 px-2 py-0.5 rounded-full">
                            <i class="fas fa-user text-[10px]"></i> Operator Daerah
                        </span>
                    @endif
                    @if(auth()->user()->isDaerah() && auth()->user()->perangkatDaerah)
                        <p class="text-xs text-blue-300 truncate mt-0.5">
                            {{ auth()->user()->perangkatDaerah->singkatan ?? auth()->user()->perangkatDaerah->nama }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 mt-3 overflow-y-auto pb-2">

            @php
                $chatUnread = (auth()->user()->isDaerah() || auth()->user()->isItTeam())
                    ? app(\App\Services\ChatService::class)->unreadConversationCount(auth()->user())
                    : 0;
            @endphp

            {{-- ── Menu untuk DAERAH (disederhanakan) ──────────── --}}
            @if(auth()->user()->isDaerah())

            @menuon('daerah','dashboard')
            <a href="{{ route('oppkpke.dashboard') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.dashboard') ? 'nav-active' : '' }}">
                <i class="fas fa-house w-5 text-center flex-shrink-0 text-yellow-300"></i>
                <span class="text-sm font-medium">Beranda</span>
            </a>
            @endmenuon

            @menuon('daerah','laporan')
            <a href="{{ route('oppkpke.laporan.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.laporan.*') ? 'nav-active' : '' }}">
                <i class="fas fa-file-pen w-5 text-center flex-shrink-0 text-green-300"></i>
                <span class="text-sm font-medium">Input Data</span>
            </a>
            @endmenuon

            @menuon('daerah','report')
            <a href="{{ route('oppkpke.report') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.report') ? 'nav-active' : '' }}">
                <i class="fas fa-table-list w-5 text-center flex-shrink-0 text-blue-300"></i>
                <span class="text-sm font-medium">Rekap Laporan</span>
            </a>
            @endmenuon

            @menuon('daerah','chat')
            <a href="{{ route('oppkpke.chat.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.chat.*') ? 'nav-active' : '' }}">
                <i class="fas fa-headset w-5 text-center flex-shrink-0 text-cyan-300"></i>
                <span class="text-sm font-medium">Chat Support IT</span>
                <span data-chat-unread class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $chatUnread ? '' : 'hidden' }}">{{ $chatUnread }}</span>
            </a>
            @endmenuon

            @menuon('daerah','panduan')
            <a href="{{ route('oppkpke.panduan') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.panduan') ? 'nav-active' : '' }}">
                <i class="fas fa-book-open w-5 text-center flex-shrink-0 text-blue-300"></i>
                <span class="text-sm font-medium">Panduan Input Laporan</span>
            </a>
            @endmenuon

            <div class="border-t border-blue-700 my-2 mx-5"></div>

            @menuon('daerah','pic')
            <a href="{{ route('oppkpke.pic.form') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.pic.form') ? 'nav-active' : '' }}">
                <i class="fas fa-id-card w-5 text-center flex-shrink-0 {{ auth()->user()->hasPicIdentity() ? 'text-green-300' : 'text-yellow-300' }}"></i>
                <span class="text-sm font-medium">Identitas PIC</span>
                @unless(auth()->user()->hasPicIdentity())
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-[9px] font-bold px-1.5 py-0.5 rounded-full">Wajib</span>
                @endunless
            </a>
            @endmenuon

            @menuon('daerah','change_password')
            <a href="{{ route('oppkpke.profile.change-password') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.profile.change-password') ? 'nav-active' : '' }}">
                <i class="fas fa-key w-5 text-center flex-shrink-0 text-orange-300"></i>
                <span class="text-sm font-medium">Ganti Password</span>
            </a>
            @endmenuon

            {{-- ── Menu untuk TIM IT (support inbox) ───────────── --}}
            @elseif(auth()->user()->isItTeam())

            <a href="{{ route('oppkpke.presentasi') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.presentasi') ? 'nav-active' : '' }}">
                <i class="fas fa-chart-line w-5 text-center flex-shrink-0 text-blue-200"></i>
                <span class="text-sm font-medium">Ikhtisar Eksekutif</span>
                <span class="ml-auto text-[10px] bg-white/15 text-blue-100 rounded px-1.5 py-0.5 font-medium">Pimpinan</span>
            </a>

            <a href="{{ route('oppkpke.chat.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.chat.*') ? 'nav-active' : '' }}">
                <i class="fas fa-comments w-5 text-center flex-shrink-0 text-cyan-300"></i>
                <span class="text-sm font-medium">Inbox Support</span>
                <span data-chat-unread class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $chatUnread ? '' : 'hidden' }}">{{ $chatUnread }}</span>
            </a>

            <a href="{{ route('oppkpke.announcements.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.announcements.*') ? 'nav-active' : '' }}">
                <i class="fas fa-bullhorn w-5 text-center flex-shrink-0 text-amber-300"></i>
                <span class="text-sm font-medium">Pengumuman</span>
            </a>

            <a href="{{ route('admin.sessions.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('admin.sessions.*') ? 'nav-active' : '' }}">
                <i class="fas fa-user-clock w-5 text-center flex-shrink-0 text-green-300"></i>
                <span class="text-sm font-medium">Sesi Login</span>
            </a>

            <a href="{{ route('admin.perangkat-daerah.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('admin.perangkat-daerah.*') ? 'nav-active' : '' }}">
                <i class="fas fa-sitemap w-5 text-center flex-shrink-0 text-indigo-300"></i>
                <span class="text-sm font-medium">Perangkat Daerah</span>
            </a>

            <a href="{{ route('admin.audit.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('admin.audit.*') ? 'nav-active' : '' }}">
                <i class="fas fa-clipboard-list w-5 text-center flex-shrink-0 text-pink-300"></i>
                <span class="text-sm font-medium">Audit Log</span>
            </a>

            <a href="{{ route('admin.menu-settings.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('admin.menu-settings.*') ? 'nav-active' : '' }}">
                <i class="fas fa-sliders w-5 text-center flex-shrink-0 text-teal-300"></i>
                <span class="text-sm font-medium">Kelola Menu</span>
            </a>

            <a href="{{ route('admin.strategi.index') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('admin.strategi.*') ? 'nav-active' : '' }}">
                <i class="fas fa-diagram-project w-5 text-center flex-shrink-0 text-orange-300"></i>
                <span class="text-sm font-medium">Kelola Strategi</span>
            </a>

            <div class="border-t border-blue-700 my-2 mx-5"></div>

            <a href="{{ route('oppkpke.profile.change-password') }}"
               class="flex items-center gap-3 px-5 py-3.5 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.profile.change-password') ? 'nav-active' : '' }}">
                <i class="fas fa-key w-5 text-center flex-shrink-0 text-orange-300"></i>
                <span class="text-sm font-medium">Ganti Password</span>
            </a>

            {{-- ── Menu untuk MASTER (lengkap) ─────────────────── --}}
            @else

            @menuon('master','dashboard')
            <a href="{{ route('oppkpke.dashboard') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.dashboard') ? 'nav-active' : '' }}">
                <i class="fas fa-chart-pie w-5 text-center flex-shrink-0"></i>
                <span class="text-sm">Dashboard</span>
            </a>
            @endmenuon

            @menuon('master','presentasi')
            <a href="{{ route('oppkpke.presentasi') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.presentasi') ? 'nav-active' : '' }}">
                <i class="fas fa-chart-line w-5 text-center flex-shrink-0 text-blue-200"></i>
                <span class="text-sm">Ikhtisar Eksekutif</span>
                <span class="ml-auto text-[10px] bg-white/15 text-blue-100 rounded px-1.5 py-0.5 font-medium">Pimpinan</span>
            </a>
            @endmenuon

            @menuon('master','laporan')
            <a href="{{ route('oppkpke.laporan.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.laporan.*') ? 'nav-active' : '' }}">
                <i class="fas fa-file-pen w-5 text-center flex-shrink-0"></i>
                <span class="text-sm">Input Laporan</span>
            </a>
            @endmenuon

            @menuon('master','statistik')
            <a href="{{ route('oppkpke.statistik') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.statistik') ? 'nav-active' : '' }}">
                <i class="fas fa-chart-bar w-5 text-center flex-shrink-0"></i>
                <span class="text-sm">Statistik</span>
            </a>
            @endmenuon

            @menuon('master','matrix')
            <a href="{{ route('oppkpke.matrix', ['tahun' => request('tahun', date('Y'))]) }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.matrix') ? 'nav-active' : '' }}">
                <i class="fas fa-table w-5 text-center flex-shrink-0 text-green-300"></i>
                <span class="text-sm">Matriks</span>
                <span class="ml-auto text-[10px] bg-white/15 text-blue-100 rounded px-1.5 py-0.5 font-medium">21 Kol</span>
            </a>
            @endmenuon

            @menuon('master','import')
            <a href="{{ route('oppkpke.import') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.import') || request()->routeIs('oppkpke.import.preview') || request()->routeIs('oppkpke.import.execute') ? 'nav-active' : '' }}">
                <i class="fas fa-file-import w-5 text-center flex-shrink-0 text-amber-300"></i>
                <span class="text-sm">Import OPPKPKE</span>
                <span class="ml-auto text-[10px] bg-white/15 text-blue-100 rounded px-1.5 py-0.5 font-medium">21K</span>
            </a>
            @endmenuon

            @menuon('master','import_rat')
            <a href="{{ route('oppkpke.import.rat') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.import.rat*') ? 'nav-active' : '' }}">
                <i class="fas fa-file-arrow-up w-5 text-center flex-shrink-0 text-green-300"></i>
                <span class="text-sm">Import RAT</span>
                <span class="ml-auto text-[10px] bg-white/15 text-blue-100 rounded px-1.5 py-0.5 font-medium">18K</span>
            </a>
            @endmenuon

            @menuon('master','import_hierarki')
            <a href="{{ route('oppkpke.import.hierarki') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.import.hierarki*') ? 'nav-active' : '' }}">
                <i class="fas fa-sitemap w-5 text-center flex-shrink-0 text-teal-300"></i>
                <span class="text-sm">Import Hierarki</span>
            </a>
            @endmenuon

            @menuon('master','export')
            <div class="border-t border-blue-700 my-2 mx-5"></div>
            <p class="px-5 pb-1 text-blue-400 text-xs uppercase tracking-wider">Export</p>
            <a href="{{ route('oppkpke.export.excel', request()->query()) }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition">
                <i class="fas fa-file-excel w-5 text-center flex-shrink-0 text-green-400"></i>
                <span class="text-sm">Export Excel</span>
            </a>
            <a href="{{ route('oppkpke.export.pdf', request()->query()) }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition">
                <i class="fas fa-file-pdf w-5 text-center flex-shrink-0 text-red-400"></i>
                <span class="text-sm">Export PDF</span>
            </a>
            @endmenuon

            <div class="border-t border-blue-700 my-2 mx-5"></div>
            <p class="px-5 pb-1 text-blue-400 text-xs uppercase tracking-wider">Administrasi</p>
            @menuon('master','users')
            <a href="{{ route('admin.users.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('admin.users.*') ? 'nav-active' : '' }}">
                <i class="fas fa-users-gear w-5 text-center flex-shrink-0 text-purple-300"></i>
                <span class="text-sm">Kelola Pengguna</span>
                @php $inactiveCount = \App\Models\User::where('is_active', false)->count(); @endphp
                @if($inactiveCount > 0)
                    <span class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full flex-shrink-0">
                        {{ $inactiveCount }}
                    </span>
                @endif
            </a>
            @endmenuon
            @menuon('master','announcements')
            <a href="{{ route('oppkpke.announcements.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.announcements.*') ? 'nav-active' : '' }}">
                <i class="fas fa-bullhorn w-5 text-center flex-shrink-0 text-amber-300"></i>
                <span class="text-sm">Pengumuman</span>
            </a>
            @endmenuon
            @menuon('master','chat')
            <a href="{{ route('oppkpke.chat.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.chat.*') ? 'nav-active' : '' }}">
                <i class="fas fa-comments w-5 text-center flex-shrink-0 text-cyan-300"></i>
                <span class="text-sm">Pantau Chat</span>
            </a>
            @endmenuon
            @menuon('master','sessions')
            <a href="{{ route('admin.sessions.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('admin.sessions.*') ? 'nav-active' : '' }}">
                <i class="fas fa-user-clock w-5 text-center flex-shrink-0 text-green-300"></i>
                <span class="text-sm">Sesi Login</span>
            </a>
            @endmenuon
            <a href="{{ route('admin.strategi.index') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('admin.strategi.*') ? 'nav-active' : '' }}">
                <i class="fas fa-diagram-project w-5 text-center flex-shrink-0 text-orange-300"></i>
                <span class="text-sm">Kelola Strategi</span>
            </a>

            <div class="border-t border-blue-700 my-2 mx-5"></div>
            @menuon('master','panduan')
            <a href="{{ route('oppkpke.panduan') }}"
               class="flex items-center gap-3 px-5 py-3 hover:bg-white/10 transition {{ request()->routeIs('oppkpke.panduan') ? 'nav-active' : '' }}">
                <i class="fas fa-book-open w-5 text-center flex-shrink-0 text-blue-300"></i>
                <span class="text-sm">Panduan Penggunaan</span>
            </a>
            @endmenuon

            @endif

        </nav>

        {{-- Logout --}}
        <div class="border-t border-blue-700 p-4">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-2 justify-center px-4 py-2 bg-red-500/20 hover:bg-red-500/40 text-red-300 hover:text-white rounded-lg transition text-sm">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Main Content ─────────────────────────────────────────────── --}}
    <main class="lg:ml-64 min-h-screen flex flex-col">

        {{-- Top Bar --}}
        <header class="bg-white shadow-sm border-b sticky top-0 z-30">
            <div class="flex items-center gap-2 px-4 py-3">

                {{-- Brand ringkas (mobile only) — pengganti hamburger --}}
                <a href="{{ route('oppkpke.dashboard') }}"
                   class="lg:hidden flex-shrink-0 w-9 h-9 rounded-lg bg-slate-700 text-white flex items-center justify-center"
                   aria-label="Beranda">
                    <i class="fas fa-hand-holding-heart text-sm"></i>
                </a>

                <div class="flex-1 min-w-0">
                    <h2 class="text-base md:text-lg font-semibold text-gray-800 truncate">
                        @yield('page-title', 'Dashboard')
                    </h2>
                    <p class="text-xs text-gray-500 truncate hidden sm:block">
                        @yield('page-subtitle', '')
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <select id="global-tahun"
                            class="rounded-lg border border-gray-300 text-xs sm:text-sm px-2 sm:px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @for($y = date('Y') + 1; $y >= 2023; $y--)
                            <option value="{{ $y }}" {{ request('tahun', date('Y')) == $y ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endfor
                    </select>
                    <a href="{{ route('oppkpke.panduan') }}"
                       title="Panduan Penggunaan"
                       class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition">
                        <i class="fas fa-circle-question"></i>
                    </a>
                </div>
            </div>
        </header>

        {{-- Flash Messages --}}
        @if(session('success'))
        <div class="mx-4 md:mx-6 mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg flex items-start gap-2 text-sm">
            <i class="fas fa-check-circle flex-shrink-0 mt-0.5"></i>
            <span>{{ session('success') }}</span>
        </div>
        @endif

        @if(session('error'))
        <div class="mx-4 md:mx-6 mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-start gap-2 text-sm">
            <i class="fas fa-exclamation-circle flex-shrink-0 mt-0.5"></i>
            <span>{{ session('error') }}</span>
        </div>
        @endif

        {{-- Pengumuman / Maintenance (semua role kecuali Tim IT) --}}
        @include('partials.announcement-banner')

        {{-- Page Content --}}
        <div class="flex-1 p-4 md:p-6">
            @yield('content')
        </div>
    </main>

    {{-- ── Bottom navigation (mobile) ───────────────────────────────── --}}
    @auth
        @include('partials.bottom-nav')
    @endauth

    {{-- ── Generic Picker Modal (reused by all views) ──────────── --}}
    <div id="modalGP" class="fixed inset-0 z-[300] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" onclick="gpClose()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm z-10 flex flex-col overflow-hidden"
             style="max-height: min(85vh, 560px)">
            <div class="px-5 py-4 border-b flex items-center justify-between flex-shrink-0">
                <h3 id="gpTitle" class="font-semibold text-gray-800 text-sm md:text-base">Pilih</h3>
                <button onclick="gpClose()" class="text-gray-400 hover:text-gray-700 p-1 -mr-1">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div id="gpSearchWrap" class="px-4 pt-3 pb-2 flex-shrink-0 hidden">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    <input id="gpSearch" type="text" placeholder="Cari..." oninput="gpRender(this.value)"
                           class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div id="gpList" class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-0"></div>
            <div class="px-4 py-2 border-t bg-gray-50 flex-shrink-0">
                <p id="gpCount" class="text-xs text-gray-400"></p>
            </div>
        </div>
    </div>

    {{-- Loading Overlay --}}
    <div id="loading-overlay" class="fixed inset-0 bg-black/50 z-[100] hidden items-center justify-center">
        <div class="bg-white rounded-xl p-8 text-center shadow-2xl">
            <div class="loader ease-linear rounded-full border-4 border-gray-200 h-12 w-12 mx-auto mb-4"></div>
            <p class="text-gray-600 font-medium">Memproses data...</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // ── CSRF ──────────────────────────────────────────────────────
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        // ── Mobile Sidebar ────────────────────────────────────────────
        function openSidebar() {
            document.getElementById('main-sidebar').classList.remove('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            document.getElementById('main-sidebar').classList.add('-translate-x-full');
            document.getElementById('sidebar-backdrop').classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Auto-close sidebar when a nav link is tapped on mobile
        document.querySelectorAll('#main-sidebar a').forEach(function (el) {
            el.addEventListener('click', function () {
                if (window.innerWidth < 1024) closeSidebar();
            });
        });

        // Restore scroll & hide backdrop on resize to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1024) {
                document.getElementById('sidebar-backdrop').classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        // ── Loading ───────────────────────────────────────────────────
        function showLoading() { $('#loading-overlay').removeClass('hidden').addClass('flex'); }
        function hideLoading() { $('#loading-overlay').removeClass('flex').addClass('hidden'); }

        // ── Currency ──────────────────────────────────────────────────
        function formatCurrency(num) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency', currency: 'IDR', minimumFractionDigits: 0
            }).format(num || 0);
        }

        // ── Toast ─────────────────────────────────────────────────────
        function showToast(message, type) {
            type = type || 'success';
            var icons  = { success: 'fa-check-circle', error: 'fa-exclamation-circle', warning: 'fa-triangle-exclamation' };
            var colors = { success: 'bg-green-500',    error:  'bg-red-500',            warning:  'bg-yellow-500' };
            var toast  = $(
                '<div class="fixed top-4 right-4 ' + (colors[type] || 'bg-green-500') + ' text-white px-5 py-3 rounded-lg shadow-xl z-[200] flex items-start gap-2 text-sm max-w-xs sm:max-w-sm">' +
                '<i class="fas ' + (icons[type] || 'fa-check-circle') + ' flex-shrink-0 mt-0.5"></i>' +
                '<span>' + message + '</span>' +
                '</div>'
            );
            $('body').append(toast);
            setTimeout(function () { toast.fadeOut(400, function () { toast.remove(); }); }, 3500);
        }

        // ── Mobile filter panel toggle (used by inner views) ──────────
        function toggleFilterPanel(panelId, chevronId) {
            var panel   = document.getElementById(panelId   || 'filter-panel');
            var chevron = document.getElementById(chevronId || 'filter-chevron');
            var isOpen  = panel.classList.contains('expanded');
            if (isOpen) {
                panel.classList.remove('expanded');
                panel.classList.add('collapsed');
                if (chevron) chevron.style.transform = '';
            } else {
                panel.classList.remove('collapsed');
                panel.classList.add('expanded');
                if (chevron) chevron.style.transform = 'rotate(180deg)';
            }
        }

        // ── Global year selector — persists via localStorage ─────────
        (function () {
            var urlYear  = new URLSearchParams(window.location.search).get('tahun');
            var lsYear   = localStorage.getItem('oppkpke_tahun');
            var selEl    = document.getElementById('global-tahun');
            if (!selEl) return;

            // Halaman import (termasuk /import/preview & /import-rat/preview) adalah
            // target form POST-only. Menambah ?tahun via window.location = GET akan
            // memicu 405 di endpoint /preview. Halaman import juga punya selektor
            // tahun sendiri, jadi auto-redirect tahun global tidak diperlukan di sini.
            var isImportPage = window.location.pathname.indexOf('/import') !== -1;

            if (urlYear) {
                // URL has explicit year → save it
                localStorage.setItem('oppkpke_tahun', urlYear);
                selEl.value = urlYear;
            } else if (lsYear && !isImportPage) {
                // No year in URL → redirect once with saved year
                var u = new URL(window.location.href);
                u.searchParams.set('tahun', lsYear);
                window.location.replace(u.toString());
                return;
            }

            // Patch all sidebar nav links to carry the active year
            var activeYear = selEl.value;
            document.querySelectorAll('#main-sidebar a[href]').forEach(function (a) {
                try {
                    var u = new URL(a.href, window.location.origin);
                    u.searchParams.set('tahun', activeYear);
                    a.href = u.toString();
                } catch (e) {}
            });
        }());

        $('#global-tahun').on('change', function () {
            localStorage.setItem('oppkpke_tahun', this.value);
            // Di halaman import (termasuk /preview yang POST-only), cukup simpan
            // pilihan tahun tanpa reload URL sekarang — reload GET ke /preview = 405.
            if (window.location.pathname.indexOf('/import') !== -1) return;
            var url = new URL(window.location.href);
            url.searchParams.set('tahun', this.value);
            window.location.href = url.toString();
        });

        // ════════════════════════════════════════════════════════════
        // GENERIC PICKER — shared by all pages
        // Usage: gpOpen({ title, targetId, items, showSearch, onSelect })
        // items: [{ value, label, sub, dot, icon, iconBg, iconColor }]
        // ════════════════════════════════════════════════════════════
        var _gpTarget = null;
        var _gpItems  = [];
        var _gpCb     = null;

        function gpOpen(cfg) {
            _gpTarget = cfg.targetId || null;
            _gpItems  = cfg.items    || [];
            _gpCb     = cfg.onSelect || null;
            document.getElementById('gpTitle').textContent = cfg.title || 'Pilih';
            var sw = document.getElementById('gpSearchWrap');
            if (cfg.showSearch) {
                sw.classList.remove('hidden');
                document.getElementById('gpSearch').value = '';
            } else { sw.classList.add('hidden'); }
            gpRender('');
            var m = document.getElementById('modalGP');
            m.classList.remove('hidden'); m.classList.add('flex');
            if (cfg.showSearch) { setTimeout(function() { document.getElementById('gpSearch').focus(); }, 150); }
        }

        function gpClose() {
            var m = document.getElementById('modalGP');
            m.classList.remove('flex'); m.classList.add('hidden');
        }

        function gpRender(q) {
            q = (q || '').trim().toLowerCase();
            var curVal = _gpTarget ? ((document.getElementById(_gpTarget) || {}).value || '') : '';
            var list = q ? _gpItems.filter(function(i) {
                return (i.label||'').toLowerCase().indexOf(q) !== -1 || (i.sub||'').toLowerCase().indexOf(q) !== -1;
            }) : _gpItems;

            var html = '';
            list.forEach(function(item, fi) {
                var origIdx = _gpItems.indexOf(item);
                var sel = String(item.value != null ? item.value : '') === String(curVal);
                html += '<button type="button" onclick="gpPick(' + origIdx + ')" ' +
                        'class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition ' + (sel ? 'bg-blue-50 border-l-4 border-blue-500' : '') + '">';
                if (item.dot) {
                    html += '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0 ' + _esc(item.dot) + '"></span>';
                } else if (item.icon) {
                    html += '<div class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center ' + _esc(item.iconBg||'bg-gray-100') + '">' +
                            '<i class="' + _esc(item.icon) + ' ' + _esc(item.iconColor||'text-gray-500') + '"></i></div>';
                }
                html += '<div class="flex-1 min-w-0">' +
                        '<p class="text-sm font-medium text-gray-800 truncate">' + _esc(item.label) + '</p>';
                if (item.sub) html += '<p class="text-xs text-gray-500 truncate">' + _esc(item.sub) + '</p>';
                html += '</div>';
                if (sel) html += '<i class="fas fa-check text-blue-500 flex-shrink-0 ml-1"></i>';
                html += '</button>';
            });
            if (!list.length) {
                html = '<div class="py-10 text-center text-gray-400"><i class="fas fa-search text-2xl mb-2 block"></i>' +
                       '<p class="text-sm">Tidak ada hasil</p></div>';
            }
            document.getElementById('gpList').innerHTML = html;
            document.getElementById('gpCount').textContent = list.length + ' pilihan tersedia';
        }

        function gpPick(idx) {
            var item = _gpItems[idx];
            if (!item) return;
            if (_gpTarget) { var el = document.getElementById(_gpTarget); if (el) el.value = item.value; }
            if (_gpCb) _gpCb(item);
            gpClose();
        }

        function _esc(s) {
            if (s == null) return '';
            var d = document.createElement('div'); d.textContent = String(s); return d.innerHTML;
        }

        // Helper: build a picker button (replaces a <select>)
        // Returns the outer HTML; injects hidden input separately
        function gpBtnClass() {
            return 'w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-blue-500 transition text-left text-sm';
        }
        // ── Sidebar chat unread badge — polling ringan ───────────────
        (function () {
            var badges = document.querySelectorAll('[data-chat-unread]');
            if (!badges.length) return;
            @if(auth()->check() && (auth()->user()->isDaerah() || auth()->user()->isItTeam()))
            var url = "{{ route('oppkpke.chat.unread-count') }}";
            function refreshUnread() {
                if (document.hidden) return;   // hemat resource saat tab tidak aktif
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) {
                        if (!d) return;
                        badges.forEach(function (b) {
                            b.textContent = d.count;
                            b.classList.toggle('hidden', !d.count);
                        });
                    })
                    .catch(function () {});
            }
            setInterval(refreshUnread, 15000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) refreshUnread(); });
            @endif
        }());
    </script>
    @stack('scripts')
</body>
</html>
