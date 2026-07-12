{{-- Daftar ringkas realisasi per Perangkat Daerah.
     Butuh: $rows (array item: nama, alokasi, realisasi, persentase), $rp (closure), $warna (closure). --}}
@forelse($rows as $pd)
    @php $c = $warna($pd['persentase']); @endphp
    <div class="flex items-center gap-3 py-2 border-b border-gray-100 last:border-0">
        <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-800 truncate" title="{{ $pd['nama'] }}">{{ $pd['nama'] }}</p>
            <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                    <div class="{{ $c[1] }} h-1.5 rounded-full" style="width: {{ min($pd['persentase'], 100) }}%"></div>
                </div>
                <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $rp($pd['realisasi']) }}</span>
            </div>
        </div>
        <span class="{{ $c[2] }} text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap">{{ number_format($pd['persentase'], 1, ',', '.') }}%</span>
    </div>
@empty
    <p class="text-sm text-gray-400 italic py-3">Belum ada data.</p>
@endforelse
