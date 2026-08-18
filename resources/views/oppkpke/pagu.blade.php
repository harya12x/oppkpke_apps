@extends('layouts.oppkpke')

@section('title', 'Kelola Pagu Anggaran')
@section('page-title', 'Kelola Pagu Anggaran')
@section('page-subtitle', 'Ubah pagu (alokasi anggaran) banyak sub kegiatan sekaligus — Tahun ' . $tahun)

@php
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $persenTerisi = $ringkasan['jumlah'] > 0 ? round(($ringkasan['terisi'] / $ringkasan['jumlah']) * 100) : 0;
@endphp

@section('content')
<div class="space-y-4 pb-28" id="pagu-root"
     data-url-simpan="{{ route('oppkpke.pagu.store') }}"
     data-tahun="{{ $tahun }}"
     data-pd="{{ $pdId }}">

    {{-- ── Cara pakai: 3 langkah, sekali baca ────────────────────── --}}
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-start gap-3">
        <i class="fas fa-lightbulb text-blue-500 mt-0.5 flex-shrink-0"></i>
        <div class="text-xs md:text-sm text-blue-900 space-y-1">
            <p class="font-semibold">Cara pakai — 3 langkah:</p>
            <ol class="list-decimal list-inside space-y-0.5 text-blue-800">
                <li>Pilih <strong>tahun anggaran</strong>@if(!$pdTerkunci) dan <strong>perangkat daerah</strong>@endif, lalu cari sub kegiatannya.</li>
                <li>Ketik angka pagu langsung pada kolom <strong>Pagu Anggaran</strong> — titik pemisah ribuan otomatis.</li>
                <li>Klik <strong>Simpan Perubahan</strong> di bilah bawah (atau tekan <kbd class="px-1 py-0.5 bg-white border rounded text-[10px]">Ctrl</kbd>+<kbd class="px-1 py-0.5 bg-white border rounded text-[10px]">S</kbd>).</li>
            </ol>
            <p class="text-blue-600 text-[11px] pt-0.5">
                Yang diubah di sini <strong>hanya pagu</strong>. Realisasi dan kolom laporan lainnya tetap seperti yang Anda isi di menu Input Laporan.
            </p>
        </div>
    </div>

    {{-- ── Penyaring ─────────────────────────────────────────────── --}}
    <form method="GET" class="bg-white rounded-xl border shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Tahun Anggaran</label>
                <select name="tahun" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($tahunTersedia as $t)
                        <option value="{{ $t }}" @selected($t == $tahun)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            @unless($pdTerkunci)
            <div class="md:col-span-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Perangkat Daerah <span class="text-red-500">*</span></label>
                <select name="perangkat_daerah_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">— Pilih perangkat daerah —</option>
                    @foreach($daftarPd as $pd)
                        <option value="{{ $pd->id }}" @selected($pdId == $pd->id)>{{ $pd->nama }}</option>
                    @endforeach
                </select>
            </div>
            @else
            <div class="md:col-span-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Perangkat Daerah</label>
                <div class="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-600 truncate">
                    <i class="fas fa-lock text-gray-400 mr-1"></i>{{ $daftarPd->first()->nama ?? '—' }}
                </div>
            </div>
            @endunless

            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Cari sub kegiatan / program</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="ketik kata kunci…"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Status Pagu</label>
                <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">Semua</option>
                    <option value="terisi" @selected(request('status') === 'terisi')>Sudah ada pagu</option>
                    <option value="kosong" @selected(request('status') === 'kosong')>Belum ada pagu</option>
                </select>
            </div>

            <div class="md:col-span-1 flex items-end gap-2">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-3 py-2 rounded-lg transition">
                    <i class="fas fa-filter"></i><span class="md:hidden ml-1">Terapkan</span>
                </button>
            </div>
        </div>
        @if(request()->hasAny(['q', 'status']))
            <div class="mt-2">
                <a href="{{ route('oppkpke.pagu.index', array_filter(['tahun' => $tahun, 'perangkat_daerah_id' => $pdTerkunci ? null : $pdId])) }}"
                   class="text-xs text-gray-500 hover:text-blue-600"><i class="fas fa-rotate-left mr-1"></i>Bersihkan pencarian</a>
            </div>
        @endif
    </form>

    @if(!$pdId)
        {{-- ── Belum memilih perangkat daerah ────────────────────── --}}
        <div class="bg-white rounded-xl border shadow-sm p-10 text-center">
            <i class="fas fa-building-columns text-4xl text-gray-300"></i>
            <p class="text-gray-700 font-semibold mt-3">Pilih perangkat daerah terlebih dahulu</p>
            <p class="text-sm text-gray-500 mt-1">Daftar sub kegiatan akan muncul setelah perangkat daerah dipilih, agar tidak menampilkan ribuan baris sekaligus.</p>
        </div>
    @else

    {{-- ── Ringkasan hasil penyaringan ────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p class="text-xs text-gray-500">Total Pagu (hasil filter)</p>
            <p class="text-xl font-bold text-gray-800 mt-1" id="ring-pagu">{{ $rp($ringkasan['pagu']) }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">realisasi {{ $rp($ringkasan['realisasi']) }}</p>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p class="text-xs text-gray-500">Sub Kegiatan</p>
            <p class="text-xl font-bold text-gray-800 mt-1" id="ring-jumlah">{{ number_format($ringkasan['jumlah'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">pada filter ini</p>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p class="text-xs text-gray-500">Sudah Ada Pagu</p>
            <p class="text-xl font-bold text-green-600 mt-1" id="ring-terisi">{{ number_format($ringkasan['terisi'], 0, ',', '.') }}</p>
            <div class="w-full bg-gray-100 rounded-full h-1.5 mt-2">
                <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $persenTerisi }}%"></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border shadow-sm p-4">
            <p class="text-xs text-gray-500">Belum Ada Pagu</p>
            <p class="text-xl font-bold text-amber-600 mt-1" id="ring-kosong">{{ number_format($ringkasan['kosong'], 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-400 mt-0.5">perlu diisi</p>
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="bg-white rounded-xl border shadow-sm p-10 text-center">
            <i class="fas fa-inbox text-4xl text-gray-300"></i>
            <p class="text-gray-700 font-semibold mt-3">Tidak ada sub kegiatan yang cocok</p>
            <p class="text-sm text-gray-500 mt-1">Coba ubah kata kunci pencarian atau status pagu.</p>
        </div>
    @else

    {{-- ── Daftar pagu, dikelompokkan per program ─────────────────── --}}
    <div class="space-y-4">
        @php $programSekarang = null; $grupIdx = 0; @endphp
        @foreach($rows as $row)
            @if($programSekarang !== $row['kode_prog'] . '|' . $row['program'])
                @if($programSekarang !== null)
                    </div></div>
                @endif
                @php
                    $programSekarang = $row['kode_prog'] . '|' . $row['program'];
                    $grupIdx++;
                @endphp
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden pagu-grup" data-grup="{{ $grupIdx }}">
                    <div class="px-4 py-3 bg-slate-50 border-b flex flex-wrap items-center gap-2">
                        <i class="fas fa-diagram-project text-slate-400"></i>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-800 leading-tight">{{ $row['program'] ?: '(Tanpa nama program)' }}</p>
                            <p class="text-[11px] text-gray-500 font-mono">{{ $row['kode_prog'] }}</p>
                        </div>
                        <div class="ml-auto text-right">
                            <p class="text-[11px] text-gray-500">Subtotal pagu program</p>
                            <p class="text-sm font-bold text-gray-800" data-subtotal="{{ $grupIdx }}">Rp 0</p>
                        </div>
                    </div>

                    {{-- Kepala kolom (desktop) --}}
                    <div class="hidden md:grid grid-cols-12 gap-2 px-4 py-2 bg-white border-b text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                        <div class="col-span-5">Sub Kegiatan</div>
                        <div class="col-span-3 text-right">Pagu Anggaran (Rp)</div>
                        <div class="col-span-2 text-right">Realisasi</div>
                        <div class="col-span-2 text-right">Sisa &amp; Serapan</div>
                    </div>

                    <div class="divide-y divide-gray-100">
            @endif

            <div class="pagu-baris grid grid-cols-1 md:grid-cols-12 gap-2 md:gap-2 px-4 py-3 border-l-4 border-transparent transition"
                 data-id="{{ $row['id'] }}"
                 data-awal="{{ $row['pagu'] }}"
                 data-realisasi="{{ $row['realisasi'] }}"
                 data-grup="{{ $grupIdx }}">

                <div class="md:col-span-5 min-w-0">
                    <p class="text-sm text-gray-800 font-medium leading-snug">{{ $row['nama'] }}</p>
                    <p class="text-[11px] text-gray-500 mt-0.5 truncate" title="{{ $row['kegiatan'] }}">
                        <i class="fas fa-angle-right text-gray-300 mr-0.5"></i>{{ $row['kegiatan'] }}
                    </p>
                    @if($row['kode'])
                        <p class="text-[10px] text-gray-400 font-mono mt-0.5">{{ $row['kode'] }}</p>
                    @endif
                </div>

                <div class="md:col-span-3">
                    <label class="md:hidden block text-[11px] font-medium text-gray-500 mb-1">Pagu Anggaran (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">Rp</span>
                        <input type="text" inputmode="numeric" autocomplete="off"
                               class="pagu-input w-full border border-gray-300 rounded-lg pl-9 pr-9 py-2 text-sm text-right font-semibold text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               value="{{ $row['pagu'] > 0 ? number_format($row['pagu'], 0, ',', '.') : '' }}"
                               placeholder="0">
                        <button type="button" title="Kembalikan ke nilai semula"
                                class="pagu-undo hidden absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 text-xs">
                            <i class="fas fa-rotate-left"></i>
                        </button>
                    </div>
                    <p class="pagu-catatan text-[11px] mt-1 hidden"></p>
                </div>

                <div class="md:col-span-2 md:text-right">
                    <span class="md:hidden text-[11px] text-gray-500">Realisasi: </span>
                    <span class="text-sm text-gray-600">{{ $rp($row['realisasi']) }}</span>
                </div>

                <div class="md:col-span-2 md:text-right">
                    <span class="md:hidden text-[11px] text-gray-500">Sisa: </span>
                    <span class="pagu-sisa text-sm font-medium text-gray-700"></span>
                    <span class="pagu-persen ml-1 text-[11px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600"></span>
                </div>
            </div>
        @endforeach
        @if($programSekarang !== null)
            </div></div>
        @endif
    </div>

    @if($paginator && $paginator->hasPages())
        <div class="bg-white rounded-xl border shadow-sm p-3">
            <p class="text-[11px] text-amber-600 mb-2">
                <i class="fas fa-circle-info mr-1"></i>Simpan dulu perubahan di halaman ini sebelum berpindah halaman.
            </p>
            {{ $paginator->links() }}
        </div>
    @endif

    @endif {{-- rows not empty --}}
    @endif {{-- pdId --}}
</div>

{{-- ── Bilah simpan (muncul saat ada perubahan) ──────────────────── --}}
<div id="pagu-bar" class="fixed bottom-0 left-0 right-0 z-40 translate-y-full transition-transform duration-200 lg:pl-64">
    <div class="bg-white border-t shadow-[0_-4px_16px_rgba(0,0,0,0.08)] px-4 py-3">
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex-shrink-0">
                <i class="fas fa-pen"></i>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-800"><span id="pagu-count">0</span> pagu diubah, belum disimpan</p>
                <p class="text-[11px] text-gray-500">Total pagu halaman ini: <span id="pagu-delta" class="font-medium">—</span></p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <button type="button" id="pagu-reset"
                        class="text-sm text-gray-600 hover:text-red-600 px-3 py-2 rounded-lg border border-gray-200 hover:border-red-200 transition">
                    <i class="fas fa-rotate-left mr-1"></i>Batalkan
                </button>
                <button type="button" id="pagu-simpan"
                        class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2 rounded-lg shadow transition">
                    <i class="fas fa-save mr-1"></i>Simpan Perubahan
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var root = document.getElementById('pagu-root');
    if (!root) return;

    var URL_SIMPAN = root.dataset.urlSimpan;
    var TAHUN      = root.dataset.tahun;
    var PD         = root.dataset.pd;
    var bar        = document.getElementById('pagu-bar');
    var baris      = Array.prototype.slice.call(document.querySelectorAll('.pagu-baris'));

    // ── Bantu format angka ────────────────────────────────────────
    function keAngka(teks) {
        var bersih = String(teks == null ? '' : teks).replace(/[^\d]/g, '');
        return bersih === '' ? 0 : parseInt(bersih, 10);
    }
    function keTeks(n) {
        return (n || 0).toLocaleString('id-ID');
    }
    function rp(n) {
        return 'Rp ' + keTeks(Math.round(n || 0));
    }

    // ── Hitung ulang satu baris (sisa, serapan, peringatan) ───────
    function hitungBaris(el) {
        var input     = el.querySelector('.pagu-input');
        var pagu      = keAngka(input.value);
        var realisasi = parseFloat(el.dataset.realisasi) || 0;
        var awal      = parseFloat(el.dataset.awal) || 0;
        var berubah   = Math.abs(pagu - awal) >= 1;

        // Sisa & serapan
        var sisa   = pagu - realisasi;
        var persen = pagu > 0 ? Math.round((realisasi / pagu) * 100) : 0;
        el.querySelector('.pagu-sisa').textContent = pagu > 0 ? rp(sisa) : '—';

        var chip = el.querySelector('.pagu-persen');
        if (pagu > 0) {
            chip.textContent = persen + '%';
            chip.className = 'pagu-persen ml-1 text-[11px] px-1.5 py-0.5 rounded-full ' +
                (persen > 100 ? 'bg-red-100 text-red-700'
                : persen >= 80 ? 'bg-green-100 text-green-700'
                : persen >= 50 ? 'bg-blue-100 text-blue-700'
                : 'bg-amber-100 text-amber-700');
        } else {
            chip.textContent = 'belum diisi';
            chip.className = 'pagu-persen ml-1 text-[11px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500';
        }

        // Catatan / peringatan di bawah input
        var catatan = el.querySelector('.pagu-catatan');
        if (pagu > 0 && realisasi > pagu) {
            catatan.textContent = 'Pagu lebih kecil dari realisasi (' + rp(realisasi) + ').';
            catatan.className   = 'pagu-catatan text-[11px] mt-1 text-red-600';
        } else if (berubah) {
            catatan.textContent = 'Semula ' + (awal > 0 ? rp(awal) : 'kosong');
            catatan.className   = 'pagu-catatan text-[11px] mt-1 text-amber-600';
        } else {
            catatan.className = 'pagu-catatan text-[11px] mt-1 hidden';
        }

        // Penanda baris berubah
        el.classList.toggle('border-amber-400', berubah);
        el.classList.toggle('bg-amber-50/50', berubah);
        el.classList.toggle('border-transparent', !berubah);
        el.querySelector('.pagu-undo').classList.toggle('hidden', !berubah);

        return { pagu: pagu, berubah: berubah };
    }

    // ── Hitung ulang subtotal program + bilah simpan ──────────────
    function hitungSemua() {
        var subtotal = {}, totalBaru = 0, totalAwal = 0, jumlahBerubah = 0;

        baris.forEach(function (el) {
            var h = hitungBaris(el);
            var g = el.dataset.grup;
            subtotal[g] = (subtotal[g] || 0) + h.pagu;
            totalBaru  += h.pagu;
            totalAwal  += parseFloat(el.dataset.awal) || 0;
            if (h.berubah) jumlahBerubah++;
        });

        Object.keys(subtotal).forEach(function (g) {
            var t = document.querySelector('[data-subtotal="' + g + '"]');
            if (t) t.textContent = rp(subtotal[g]);
        });

        document.getElementById('pagu-count').textContent = jumlahBerubah;
        var selisih = totalBaru - totalAwal;
        document.getElementById('pagu-delta').textContent =
            rp(totalAwal) + ' → ' + rp(totalBaru) + (selisih ? ' (' + (selisih > 0 ? '+' : '−') + rp(Math.abs(selisih)).replace('Rp ', 'Rp ') + ')' : '');

        bar.classList.toggle('translate-y-full', jumlahBerubah === 0);
        return jumlahBerubah;
    }

    // ── Interaksi input ───────────────────────────────────────────
    baris.forEach(function (el, idx) {
        var input = el.querySelector('.pagu-input');

        input.addEventListener('input', function () {
            var angka = keAngka(input.value);
            input.value = angka > 0 ? keTeks(angka) : '';
            hitungSemua();
        });

        // Enter = lanjut ke baris berikutnya (alur input cepat).
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                var next = baris[idx + 1];
                if (next) { next.querySelector('.pagu-input').focus(); next.querySelector('.pagu-input').select(); }
                else { document.getElementById('pagu-simpan').focus(); }
            }
        });

        input.addEventListener('focus', function () { input.select(); });

        el.querySelector('.pagu-undo').addEventListener('click', function () {
            var awal = parseFloat(el.dataset.awal) || 0;
            input.value = awal > 0 ? keTeks(awal) : '';
            hitungSemua();
            input.focus();
        });
    });

    // ── Batalkan semua perubahan ──────────────────────────────────
    document.getElementById('pagu-reset').addEventListener('click', function () {
        baris.forEach(function (el) {
            var awal = parseFloat(el.dataset.awal) || 0;
            el.querySelector('.pagu-input').value = awal > 0 ? keTeks(awal) : '';
        });
        hitungSemua();
        showToast('Perubahan dibatalkan', 'warning');
    });

    // ── Simpan ────────────────────────────────────────────────────
    var sedangSimpan = false;
    function simpan() {
        if (sedangSimpan) return;

        var items = [], adaMinus = 0;
        baris.forEach(function (el) {
            var pagu = keAngka(el.querySelector('.pagu-input').value);
            var awal = parseFloat(el.dataset.awal) || 0;
            if (Math.abs(pagu - awal) >= 1) {
                items.push({ id: el.dataset.id, pagu: pagu });
                if (pagu > 0 && (parseFloat(el.dataset.realisasi) || 0) > pagu) adaMinus++;
            }
        });

        if (!items.length) { showToast('Belum ada perubahan untuk disimpan', 'warning'); return; }
        if (adaMinus && !confirm(adaMinus + ' baris punya pagu lebih kecil dari realisasinya. Tetap simpan?')) return;

        var btn = document.getElementById('pagu-simpan');
        var isi = btn.innerHTML;
        sedangSimpan = true;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Menyimpan...';

        $.ajax({
            url: URL_SIMPAN,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                tahun: TAHUN,
                perangkat_daerah_id: PD,
                q: @json(request('q')),
                status: @json(request('status')),
                items: items
            }
        })
        .done(function (res) {
            // Nilai baru jadi acuan berikutnya → penanda "berubah" hilang.
            baris.forEach(function (el) {
                var h = res.hasil && res.hasil[el.dataset.id];
                if (h) el.dataset.awal = h.pagu;
            });
            if (res.ringkasan) {
                document.getElementById('ring-pagu').textContent    = rp(res.ringkasan.pagu);
                document.getElementById('ring-terisi').textContent  = keTeks(res.ringkasan.terisi);
                document.getElementById('ring-kosong').textContent  = keTeks(res.ringkasan.kosong);
            }
            hitungSemua();
            showToast(res.message || 'Tersimpan', 'success');
        })
        .fail(function (xhr) {
            var pesan = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Gagal menyimpan pagu';
            showToast(pesan, 'error');
        })
        .always(function () {
            sedangSimpan = false;
            btn.disabled = false;
            btn.innerHTML = isi;
        });
    }

    document.getElementById('pagu-simpan').addEventListener('click', simpan);

    // Ctrl+S / Cmd+S
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') {
            e.preventDefault();
            simpan();
        }
    });

    // Jangan sampai perubahan hilang tanpa sadar.
    window.addEventListener('beforeunload', function (e) {
        if (document.getElementById('pagu-count').textContent !== '0') {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    hitungSemua();
})();
</script>
@endpush
