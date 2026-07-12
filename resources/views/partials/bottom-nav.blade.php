{{-- Bottom navigation (hanya mobile / tablet — disembunyikan di layar lg ke atas).
     Palet natural & kontras tinggi untuk keterbacaan pengguna lanjut usia.
     Item aktif: biru tua tenang; nonaktif: abu-abu. Tombol "Lainnya" membuka
     sidebar lengkap (yang tetap dikelola @menuon oleh Tim IT). --}}
@php
    $u = auth()->user();
    // Definisi item per role: [label, ikon, route|null, aktifJika(pola), warnaAktif]
    if ($u->isDaerah()) {
        $items = [
            ['Beranda', 'fa-house',        route('oppkpke.dashboard'),      'oppkpke.dashboard'],
            ['Input',   'fa-pen-to-square', route('oppkpke.laporan.index'), 'oppkpke.laporan.*'],
            ['Rekap',   'fa-table-list',   route('oppkpke.report'),         'oppkpke.report'],
            ['Chat',    'fa-comments',     route('oppkpke.chat.index'),     'oppkpke.chat.*'],
        ];
    } elseif ($u->isItTeam()) {
        $items = [
            ['Ikhtisar', 'fa-chart-line',  route('oppkpke.presentasi'),         'oppkpke.presentasi'],
            ['Inbox',    'fa-comments',    route('oppkpke.chat.index'),         'oppkpke.chat.*'],
            ['Info',     'fa-bullhorn',    route('oppkpke.announcements.index'),'oppkpke.announcements.*'],
            ['Audit',    'fa-clipboard-list', route('admin.audit.index'),       'admin.audit.*'],
        ];
    } else { // master
        $items = [
            ['Dashboard', 'fa-gauge',        route('oppkpke.dashboard'),   'oppkpke.dashboard'],
            ['Ikhtisar',  'fa-chart-line',   route('oppkpke.presentasi'),  'oppkpke.presentasi'],
            ['Laporan',   'fa-pen-to-square', route('oppkpke.laporan.index'), 'oppkpke.laporan.*'],
            ['Matriks',   'fa-table',        route('oppkpke.matrix', ['tahun' => request('tahun', date('Y'))]), 'oppkpke.matrix'],
        ];
    }
@endphp

<nav id="bottom-nav"
     class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-200 flex items-stretch justify-around"
     role="navigation" aria-label="Navigasi bawah">

    @foreach($items as [$label, $icon, $href, $pattern])
        @php $active = request()->routeIs($pattern); @endphp
        <a href="{{ $href }}"
           class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 min-h-[3.5rem] transition
                  {{ $active ? 'text-blue-800' : 'text-gray-400 hover:text-gray-600' }}"
           @if($active) aria-current="page" @endif>
            <i class="fas {{ $icon }} text-lg leading-none {{ $active ? '' : 'opacity-90' }}"></i>
            <span class="text-[11px] font-medium leading-none">{{ $label }}</span>
        </a>
    @endforeach

    {{-- Lainnya → halaman Menu lengkap --}}
    @php $menuActive = request()->routeIs('oppkpke.menu'); @endphp
    <a href="{{ route('oppkpke.menu') }}"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2 min-h-[3.5rem] transition
              {{ $menuActive ? 'text-blue-800' : 'text-gray-400 hover:text-gray-600' }}"
       @if($menuActive) aria-current="page" @endif
       aria-label="Menu lainnya">
        <i class="fas fa-bars text-lg leading-none"></i>
        <span class="text-[11px] font-medium leading-none">Lainnya</span>
    </a>
</nav>
