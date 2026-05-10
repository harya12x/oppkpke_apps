@extends('layouts.oppkpke')

@section('title', 'Input Laporan OPPKPKE')
@section('page-title', 'Input Data Laporan OPPKPKE')
@section('page-subtitle', 'Kelola dan input data laporan pengentasan kemiskinan')

@section('content')

{{-- ── Mobile: filter toggle button ──────────────────────── --}}
<button onclick="toggleFilterPanel()"
        class="lg:hidden w-full mb-3 flex items-center justify-between bg-white rounded-xl border shadow-sm px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition"
        aria-expanded="false">
    <span class="flex items-center gap-2">
        <i class="fas fa-filter text-blue-500"></i> Filter & Pencarian
    </span>
    <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200" id="filter-chevron"></i>
</button>

<div class="flex flex-col lg:flex-row gap-4 lg:gap-6">

    {{-- ── Filter Sidebar ───────────────────────────────── --}}
    <div id="filter-panel"
         class="filter-panel-mobile collapsed lg:!max-h-none lg:!opacity-100 lg:block lg:w-72 lg:flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border p-4 lg:sticky lg:top-24">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center text-sm">
                <i class="fas fa-filter mr-2 text-blue-500"></i> Filter
            </h3>

            <form id="filter-form" class="space-y-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pencarian</label>
                    <input type="text" name="search" id="filter-search" placeholder="Cari..."
                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                {{-- Strategi --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Strategi</label>
                    <input type="hidden" name="strategi_id" id="filter-strategi" value="">
                    <button type="button" onclick="idxOpenStrategi()"
                            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
                        <i class="fas fa-sitemap text-gray-400 text-xs flex-shrink-0"></i>
                        <span id="lbl-strategi" class="flex-1 truncate text-gray-400">Semua Strategi</span>
                        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                    </button>
                </div>

                {{-- Perangkat Daerah --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Perangkat Daerah</label>
                    <input type="hidden" name="perangkat_daerah_id" id="filter-perangkat" value="">
                    <button type="button" onclick="idxOpenPerangkat()"
                            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
                        <i class="fas fa-building text-gray-400 text-xs flex-shrink-0"></i>
                        <span id="lbl-perangkat" class="flex-1 truncate text-gray-400">Semua Perangkat</span>
                        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                    </button>
                </div>

                {{-- Tahun + Status --}}
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tahun</label>
                        <input type="hidden" name="tahun" id="filter-tahun" value="{{ request('tahun', date('Y')) }}">
                        <button type="button" onclick="idxOpenTahun()"
                                class="w-full flex items-center gap-1.5 border border-gray-300 rounded-lg px-2.5 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
                            <i class="fas fa-calendar text-gray-400 text-xs flex-shrink-0"></i>
                            <span id="lbl-tahun" class="flex-1 font-medium text-gray-700">{{ request('tahun', date('Y')) }}</span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <input type="hidden" name="status" id="filter-status" value="">
                        <button type="button" onclick="idxOpenStatus()"
                                class="w-full flex items-center gap-1.5 border border-gray-300 rounded-lg px-2.5 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
                            <i class="fas fa-circle-half-stroke text-gray-400 text-xs flex-shrink-0"></i>
                            <span id="lbl-status" class="flex-1 text-gray-400 text-xs">Semua</span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                        </button>
                    </div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg text-sm hover:bg-blue-700 transition">
                        <i class="fas fa-search mr-1"></i> Cari
                    </button>
                    <button type="button" id="btn-reset" class="px-3 py-2 border rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-undo text-gray-500"></i>
                    </button>
                </div>
            </form>

            {{-- Summary --}}
            <div class="mt-4 pt-4 border-t">
                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">Ringkasan</h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total</span>
                        <span id="stat-total" class="font-semibold">0</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Terisi</span>
                        <span id="stat-filled" class="font-semibold text-green-600">0</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Kosong</span>
                        <span id="stat-empty" class="font-semibold text-orange-500">0</span>
                    </div>
                    <div class="pt-2 border-t">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-500">Progress</span>
                            <span id="stat-persen" class="font-semibold">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div id="stat-progress-bar" class="bg-green-500 h-2 rounded-full transition-all" style="width:0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Main Content ──────────────────────────────────── --}}
    <div class="flex-1 min-w-0">
        <div class="bg-white rounded-xl shadow-sm border p-3 md:p-4 mb-4 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span id="result-count" class="text-xl md:text-2xl font-bold text-gray-800">0</span>
                <span class="text-gray-500 text-sm">sub kegiatan</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button id="btn-expand"   class="px-2 md:px-3 py-1.5 text-xs border rounded hover:bg-gray-50">
                    <i class="fas fa-expand-alt mr-1"></i><span class="hidden sm:inline">Expand</span>
                </button>
                <button id="btn-collapse" class="px-2 md:px-3 py-1.5 text-xs border rounded hover:bg-gray-50">
                    <i class="fas fa-compress-alt mr-1"></i><span class="hidden sm:inline">Collapse</span>
                </button>
                <button type="button" onclick="openInputModalNew()"
                        class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs md:text-sm hover:bg-green-700 transition">
                    <i class="fas fa-plus mr-1"></i> Tambah
                </button>
                <a href="{{ route('oppkpke.export.excel', request()->query()) }}"
                   class="px-2 md:px-3 py-1.5 text-xs bg-emerald-600 text-white rounded hover:bg-emerald-700 transition">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>

        <div id="data-container" class="space-y-3">
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                <p>Memuat data...</p>
            </div>
        </div>
    </div>
</div>

{{-- ── Modal Input / Edit ──────────────────────────────────── --}}
<div id="input-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-3 md:p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[92vh] overflow-hidden flex flex-col">

        <div id="modal-header" class="bg-gradient-to-r from-blue-600 to-blue-700 px-4 py-3 flex justify-between items-start flex-shrink-0">
            <div class="min-w-0">
                <h3 class="font-semibold text-white text-sm md:text-base" id="modal-title">Input Data</h3>
                <p class="text-xs text-white/70 mt-0.5 truncate" id="modal-subtitle"></p>
            </div>
            <button onclick="closeModal()" class="text-white/70 hover:text-white ml-2 flex-shrink-0">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>

        <div class="overflow-y-auto flex-1 p-4">
            <form id="input-form">
                @csrf
                <input type="hidden" name="id" id="input-id">
                <input type="hidden" name="sub_kegiatan_id" id="input-sub-kegiatan-id">
                <input type="hidden" name="tahun" id="input-tahun">

                {{-- Pilih Sub Kegiatan (tambah baru) --}}
                <div id="section-select" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-tasks mr-1 text-indigo-500"></i> Pilih Sub Kegiatan
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div class="sm:col-span-2">
                            <select id="input-sub-kegiatan-select" class="w-full border rounded-lg text-sm p-2.5">
                                <option value="">-- Cari Sub Kegiatan --</option>
                            </select>
                        </div>
                        {{-- Tahun picker (modal) --}}
                        <div>
                            <input type="hidden" id="input-tahun-select" value="{{ date('Y') }}">
                            <button type="button" onclick="idxOpenModalTahun()"
                                    class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white hover:bg-gray-50 transition text-left text-sm">
                                <i class="fas fa-calendar text-gray-400 text-xs flex-shrink-0"></i>
                                <span id="lbl-modal-tahun" class="flex-1 font-medium text-gray-700">{{ date('Y') }}</span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Grid Form --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Durasi Pemberian</label>
                        <input type="text" name="durasi_pemberian" id="input-durasi" placeholder="12 bulan"
                               class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Besaran Manfaat</label>
                        <input type="text" name="besaran_manfaat" id="input-besaran" placeholder="Rp 500.000"
                               class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Jenis Bantuan</label>
                        <input type="text" name="jenis_bantuan" id="input-jenis" placeholder="Tunai/Barang"
                               class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                {{-- Aktivitas (Kolom 8-10 Matriks RAT) --}}
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-semibold text-indigo-700 uppercase tracking-wide">Aktivitas</span>
                        <span class="flex-1 h-px bg-indigo-100"></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                <i class="fas fa-arrow-right text-green-500 text-xs mr-0.5"></i> Langsung
                            </label>
                            <textarea name="aktivitas_langsung" id="input-akt-langsung" rows="3"
                                      placeholder="Contoh: Pemberian bantuan sosial tunai kepada KPM..."
                                      class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                <i class="fas fa-arrows-left-right text-yellow-500 text-xs mr-0.5"></i> Tidak Langsung
                            </label>
                            <textarea name="aktivitas_tidak_langsung" id="input-akt-tidak-langsung" rows="3"
                                      placeholder="Contoh: Bimbingan teknis SDM Kesos..."
                                      class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                <i class="fas fa-gear text-orange-500 text-xs mr-0.5"></i> Penunjang
                            </label>
                            <textarea name="aktivitas_penunjang" id="input-akt-penunjang" rows="3"
                                      placeholder="Contoh: Pengadaan sarana prasarana..."
                                      class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Sasaran & Penerima --}}
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Penerima</span>
                        <span class="flex-1 h-px bg-gray-200"></span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Jml Sasaran</label>
                            <input type="number" name="jumlah_sasaran" id="input-sasaran"
                                   class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Satuan</label>
                            <input type="text" name="satuan_sasaran" id="input-satuan" placeholder="KK/Jiwa"
                                   class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Langsung</label>
                            <input type="number" name="penerima_langsung" id="input-langsung" step="0.01" value="0"
                                   class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tdk Langsung</label>
                            <input type="number" name="penerima_tidak_langsung" id="input-tidak-langsung" step="0.01" value="0"
                                   class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Penunjang</label>
                            <input type="number" name="penerima_penunjang" id="input-penunjang" step="0.01" value="0"
                                   class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sumber Pembiayaan</label>
                        <input type="hidden" name="sumber_pembiayaan" id="input-sumber" value="APBD">
                        <button type="button" onclick="idxOpenSumber()"
                                class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2.5 bg-white hover:bg-gray-50 transition text-left text-sm">
                            <i class="fas fa-wallet text-gray-400 text-xs flex-shrink-0"></i>
                            <span id="lbl-sumber" class="flex-1 font-medium text-gray-700">APBD</span>
                            <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
                        </button>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sifat Bantuan</label>
                        <input type="text" name="sifat_bantuan" id="input-sifat" placeholder="Rutin/Insidentil"
                               class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Lokasi</label>
                        <input type="text" name="lokasi" id="input-lokasi" placeholder="Seluruh Kecamatan"
                               class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                {{-- Anggaran --}}
                <div class="bg-gray-50 rounded-lg p-3 md:p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Alokasi Anggaran <span class="text-red-500">*</span></label>
                            <input type="number" name="alokasi_anggaran" id="input-alokasi" step="0.01" value="0" required
                                   class="w-full border rounded-lg text-sm p-2.5 font-mono bg-white focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Realisasi Sem 1</label>
                            <input type="number" name="realisasi_sem1" id="input-real-sem1" step="0.01" value="0"
                                   class="w-full border rounded-lg text-sm p-2.5 font-mono bg-white focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Realisasi Sem 2</label>
                            <input type="number" name="realisasi_sem2" id="input-real-sem2" step="0.01" value="0"
                                   class="w-full border rounded-lg text-sm p-2.5 font-mono bg-white focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-gray-200">
                        <div>
                            <span class="text-xs text-gray-500">Total Realisasi</span>
                            <div id="preview-total" class="text-lg md:text-xl font-bold text-green-600">Rp 0</div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs text-gray-500">Persentase</span>
                            <div class="flex items-center gap-2">
                                <div class="w-24 md:w-32 bg-gray-200 rounded-full h-2">
                                    <div id="preview-progress" class="bg-green-500 h-2 rounded-full" style="width:0%"></div>
                                </div>
                                <span id="preview-persen" class="text-lg md:text-xl font-bold text-blue-600">0%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-gray-50 px-4 py-3 border-t flex justify-end gap-2 flex-shrink-0">
            <button onclick="closeModal()" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100 transition">Batal</button>
            <button onclick="saveData()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition">
                <i class="fas fa-save mr-1"></i> Simpan
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Picker item arrays ──────────────────────────────────────
var _idxStrategiItems = [{ value: '', label: 'Semua Strategi' }].concat(
    @json(collect($filterOptions['strategi'] ?? [])->map(fn($s) => ['value' => $s->id, 'label' => $s->nama])->values())
);
var _idxPdItems = [{ value: '', label: 'Semua Perangkat' }].concat(
    @json(collect($filterOptions['perangkat_daerah'] ?? [])->map(fn($pd) => ['value' => $pd->id, 'label' => $pd->nama])->values())
);
var _idxTahunItems = (function() {
    var a = [], cur = new Date().getFullYear();
    for (var y = cur + 1; y >= cur - 5; y--) a.push({ value: String(y), label: String(y) });
    return a;
})();
var _idxStatusItems = [
    { value: '', label: 'Semua' },
    { value: 'filled', label: 'Terisi', dot: 'bg-green-500' },
    { value: 'empty',  label: 'Kosong', dot: 'bg-orange-400' }
];
var _idxSumberItems = [
    { value: 'APBD', label: 'APBD' }, { value: 'APBN', label: 'APBN' },
    { value: 'DAK',  label: 'DAK'  }, { value: 'Dana Desa', label: 'Dana Desa' },
    { value: 'Lainnya', label: 'Lainnya' }
];

function idxSetBtn(field, value, label) {
    document.getElementById('filter-' + field).value = value != null ? value : '';
    var lbl = document.getElementById('lbl-' + field);
    lbl.textContent = label;
    lbl.style.color = (value !== '' && value != null) ? '#374151' : '#9ca3af';
}

function idxOpenStrategi() {
    gpOpen({
        title: 'Pilih Strategi',
        targetId: 'filter-strategi',
        items: _idxStrategiItems,
        showSearch: false,
        onSelect: function (item) {
            idxSetBtn('strategi', item.value, item.label);
            idxSetBtn('perangkat', '', 'Semua Perangkat');
            _idxPdItems = [{ value: '', label: 'Semua Perangkat' }];
            if (item.value) {
                $.get('{{ route("oppkpke.options.perangkat-daerah") }}', { strategi_id: item.value }, function (d) {
                    _idxPdItems = [{ value: '', label: 'Semua Perangkat' }].concat(d.map(function (i) { return { value: i.id, label: i.nama }; }));
                });
            }
        }
    });
}

function idxOpenPerangkat() {
    gpOpen({
        title: 'Pilih Perangkat Daerah',
        targetId: 'filter-perangkat',
        items: _idxPdItems,
        showSearch: _idxPdItems.length > 6,
        onSelect: function (item) { idxSetBtn('perangkat', item.value, item.label); }
    });
}

function idxOpenTahun() {
    gpOpen({
        title: 'Pilih Tahun',
        targetId: 'filter-tahun',
        items: _idxTahunItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('filter-tahun').value = item.value;
            var lbl = document.getElementById('lbl-tahun');
            lbl.textContent = item.label;
            lbl.style.color = '#374151';
        }
    });
}

function idxOpenStatus() {
    gpOpen({
        title: 'Filter Status',
        targetId: 'filter-status',
        items: _idxStatusItems,
        showSearch: false,
        onSelect: function (item) { idxSetBtn('status', item.value, item.label); }
    });
}

function idxOpenModalTahun() {
    gpOpen({
        title: 'Pilih Tahun',
        targetId: 'input-tahun-select',
        items: _idxTahunItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('input-tahun-select').value = item.value;
            document.getElementById('input-tahun').value = item.value;
            document.getElementById('lbl-modal-tahun').textContent = item.label;
        }
    });
}

function idxOpenSumber() {
    gpOpen({
        title: 'Pilih Sumber Pembiayaan',
        targetId: 'input-sumber',
        items: _idxSumberItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('input-sumber').value = item.value;
            document.getElementById('lbl-sumber').textContent = item.label;
        }
    });
}

$(function () {
    loadData();

    $('#filter-form').on('submit', function (e) { e.preventDefault(); loadData(); });
    $('#btn-reset').on('click', function () {
        idxSetBtn('strategi',  '', 'Semua Strategi');
        idxSetBtn('perangkat', '', 'Semua Perangkat');
        idxSetBtn('status',    '', 'Semua');
        var curY = String(new Date().getFullYear());
        document.getElementById('filter-tahun').value = curY;
        document.getElementById('lbl-tahun').textContent = curY;
        document.getElementById('lbl-tahun').style.color = '#374151';
        document.getElementById('filter-search').value = '';
        _idxPdItems = [{ value: '', label: 'Semua Perangkat' }].concat(
            @json(collect($filterOptions['perangkat_daerah'] ?? [])->map(fn($pd) => ['value' => $pd->id, 'label' => $pd->nama])->values())
        );
        loadData();
    });
    $('#btn-expand').on('click', function () { $('.accordion-content').slideDown(200); });
    $('#btn-collapse').on('click', function () { $('.accordion-content').slideUp(200); });
    $('#input-real-sem1, #input-real-sem2, #input-alokasi').on('input', calculatePreview);

    $('#input-sub-kegiatan-select').select2({
        dropdownParent: $('#input-modal'),
        placeholder: '-- Cari Sub Kegiatan --',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route("oppkpke.options.sub-kegiatan") }}',
            dataType: 'json',
            delay: 300,
            data: params => ({ search: params.term, tahun: $('#input-tahun-select').val() }),
            processResults: data => ({
                results: data.map(item => ({
                    id: item.id,
                    text: item.nama_sub_kegiatan,
                    extra: item.kegiatan?.program?.perangkat_daerah?.nama || ''
                }))
            })
        },
        templateResult: item => item.loading ? item.text :
            $('<div><div class="font-medium text-sm">' + item.text + '</div><div class="text-xs text-gray-400">' + (item.extra || '') + '</div></div>')
    }).on('change', function () { $('#input-sub-kegiatan-id').val($(this).val()); });
});

var _autoOpenId = {{ (int) request('open', 0) }};

function loadData() {
    showLoading();
    $.get('{{ route("oppkpke.explorer.data") }}', $('#filter-form').serialize())
        .done(function (res) {
            hideLoading();
            renderData(res.data);
            updateStats(res.data);
            if (_autoOpenId) {
                var target = res.data.find(function (d) { return d.id == _autoOpenId; });
                if (target) {
                    setTimeout(function () { openInputModal(target); }, 450);
                }
                _autoOpenId = 0;
            }
        })
        .fail(function () { hideLoading(); showToast('Gagal memuat data', 'error'); });
}

function updateStats(data) {
    var filled = 0, empty = 0, totalAlokasi = 0, totalRealisasi = 0;
    data.forEach(function (item) {
        var lap = item.laporan && item.laporan[0];
        if (lap && parseFloat(lap.alokasi_anggaran) > 0) {
            filled++;
            totalAlokasi  += parseFloat(lap.alokasi_anggaran)  || 0;
            totalRealisasi += parseFloat(lap.realisasi_total) || 0;
        } else { empty++; }
    });
    var persen = totalAlokasi > 0 ? ((totalRealisasi / totalAlokasi) * 100).toFixed(1) : 0;
    $('#result-count').text(data.length);
    $('#stat-total').text(data.length);
    $('#stat-filled').text(filled);
    $('#stat-empty').text(empty);
    $('#stat-persen').text(persen + '%');
    $('#stat-progress-bar').css('width', Math.min(persen, 100) + '%');
}

function renderData(data) {
    if (!data.length) {
        $('#data-container').html('<div class="text-center py-12 text-gray-400"><i class="fas fa-inbox text-4xl mb-3"></i><p>Tidak ada data</p></div>');
        return;
    }
    var tree = {};
    data.forEach(function (item) {
        var s   = item.kegiatan?.program?.strategi?.nama   || '-';
        var sId = item.kegiatan?.program?.strategi?.id     || 1;
        var pd  = item.kegiatan?.program?.perangkat_daerah?.nama || '-';
        var pr  = item.kegiatan?.program?.nama_program     || '-';
        var kg  = item.kegiatan?.nama_kegiatan             || '-';
        tree[s] = tree[s] || { id: sId, perangkat: {} };
        tree[s].perangkat[pd] = tree[s].perangkat[pd] || { program: {} };
        tree[s].perangkat[pd].program[pr] = tree[s].perangkat[pd].program[pr] || { kegiatan: {} };
        tree[s].perangkat[pd].program[pr].kegiatan[kg] = tree[s].perangkat[pd].program[pr].kegiatan[kg] || [];
        tree[s].perangkat[pd].program[pr].kegiatan[kg].push(item);
    });

    var colors = {
        1: { bg: 'bg-blue-600',   light: 'bg-blue-50',   text: 'text-blue-600' },
        2: { bg: 'bg-green-600',  light: 'bg-green-50',  text: 'text-green-600' },
        3: { bg: 'bg-orange-500', light: 'bg-orange-50', text: 'text-orange-600' }
    };

    var html = '';
    Object.keys(tree).forEach(function (strategi) {
        var sData   = tree[strategi];
        var c       = colors[sData.id] || colors[1];
        var pdCount = Object.keys(sData.perangkat).length;

        html += '<div class="bg-white rounded-xl shadow-sm border overflow-hidden">';
        html += '<div class="px-3 md:px-4 py-3 ' + c.light + ' cursor-pointer flex items-center justify-between" onclick="$(this).next(\'.accordion-content\').slideToggle(200)">';
        html += '<div class="flex items-center gap-2 min-w-0">';
        html += '<div class="w-7 h-7 md:w-8 md:h-8 ' + c.bg + ' rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-layer-group text-white text-xs"></i></div>';
        html += '<div class="min-w-0"><div class="font-semibold ' + c.text + ' text-sm truncate">' + strategi + '</div>';
        html += '<div class="text-xs text-gray-500">' + pdCount + ' Perangkat Daerah</div></div>';
        html += '</div><i class="fas fa-chevron-down text-gray-400 flex-shrink-0"></i></div>';
        html += '<div class="accordion-content" style="display:none;">';

        Object.keys(sData.perangkat).forEach(function (perangkat) {
            var pdData  = sData.perangkat[perangkat];
            var prCount = Object.keys(pdData.program).length;

            html += '<div class="border-t">';
            html += '<div class="px-3 md:px-4 py-2 bg-gray-50 cursor-pointer flex items-center justify-between ml-2 md:ml-4" onclick="$(this).next(\'.accordion-content\').slideToggle(200)">';
            html += '<div class="flex items-center gap-2 min-w-0"><i class="fas fa-building text-gray-400 flex-shrink-0"></i>';
            html += '<span class="font-medium text-gray-700 text-sm truncate">' + perangkat + '</span>';
            html += '<span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded flex-shrink-0">' + prCount + ' Program</span>';
            html += '</div><i class="fas fa-chevron-down text-gray-300 text-xs flex-shrink-0"></i></div>';
            html += '<div class="accordion-content" style="display:none;">';

            Object.keys(pdData.program).forEach(function (program) {
                var prData  = pdData.program[program];
                var kgCount = Object.keys(prData.kegiatan).length;

                html += '<div class="border-t border-dashed">';
                html += '<div class="px-3 md:px-4 py-2 cursor-pointer flex items-center justify-between ml-4 md:ml-8 hover:bg-gray-50" onclick="$(this).next(\'.accordion-content\').slideToggle(200)">';
                html += '<div class="flex items-center gap-2 min-w-0"><i class="fas fa-folder text-yellow-500 text-sm flex-shrink-0"></i>';
                html += '<span class="text-sm text-gray-700 truncate">' + program + '</span>';
                html += '<span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded flex-shrink-0">' + kgCount + ' Kegiatan</span>';
                html += '</div><i class="fas fa-chevron-down text-gray-300 text-xs flex-shrink-0"></i></div>';
                html += '<div class="accordion-content" style="display:none;">';

                Object.keys(prData.kegiatan).forEach(function (kegiatan) {
                    var items       = prData.kegiatan[kegiatan];
                    var filledCount = items.filter(function (i) { return i.laporan && i.laporan[0] && i.laporan[0].alokasi_anggaran > 0; }).length;

                    html += '<div class="border-t border-dotted">';
                    html += '<div class="px-3 md:px-4 py-2 cursor-pointer flex items-center justify-between ml-6 md:ml-12 hover:bg-blue-50/50" onclick="$(this).next(\'.accordion-content\').slideToggle(200)">';
                    html += '<div class="flex items-center gap-2 min-w-0"><i class="fas fa-clipboard-list text-blue-400 text-sm flex-shrink-0"></i>';
                    html += '<span class="text-sm text-gray-600 truncate">' + kegiatan + '</span></div>';
                    html += '<div class="flex items-center gap-1.5 flex-shrink-0">';
                    html += '<span class="text-xs ' + (filledCount === items.length ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700') + ' px-2 py-0.5 rounded">' + filledCount + '/' + items.length + '</span>';
                    html += '<i class="fas fa-chevron-down text-gray-300 text-xs"></i></div></div>';
                    html += '<div class="accordion-content bg-white" style="display:none;">';

                    items.forEach(function (item) {
                        var lap    = (item.laporan && item.laporan[0]) || {};
                        var filled = lap.alokasi_anggaran > 0;
                        var persen = filled ? ((lap.realisasi_total / lap.alokasi_anggaran) * 100).toFixed(0) : 0;
                        var itemJson = JSON.stringify(item).replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        html += '<div class="ml-8 md:ml-16 px-3 md:px-4 py-2 border-t border-gray-100 flex items-start justify-between gap-2 hover:bg-gray-50 ' + (!filled ? 'bg-yellow-50/30' : '') + '">';
                        html += '<div class="flex-1 min-w-0">';
                        html += '<div class="flex flex-wrap items-center gap-1.5">';
                        html += '<span class="text-sm ' + (filled ? 'text-gray-700' : 'text-gray-500') + '">' + item.nama_sub_kegiatan + '</span>';
                        if (!filled) html += '<span class="text-xs bg-yellow-100 text-yellow-600 px-1.5 py-0.5 rounded">Kosong</span>';
                        html += '</div>';
                        if (filled) {
                            html += '<div class="flex flex-wrap items-center gap-2 md:gap-4 mt-1 text-xs text-gray-400">';
                            html += '<span>Alokasi: ' + formatCurrency(lap.alokasi_anggaran) + '</span>';
                            html += '<span class="text-green-600">Realisasi: ' + persen + '%</span>';
                            html += '</div>';
                        }
                        html += '</div>';
                        html += '<button onclick=\'openInputModal(' + itemJson + ')\' class="flex-shrink-0 px-2 md:px-3 py-1 text-xs ' + (filled ? 'bg-blue-100 text-blue-600 hover:bg-blue-200' : 'bg-green-100 text-green-600 hover:bg-green-200') + ' rounded transition">';
                        html += filled ? '<i class="fas fa-edit"></i>' : '<i class="fas fa-plus"></i>';
                        html += '</button></div>';
                    });

                    html += '</div></div>';
                });

                html += '</div></div>';
            });

            html += '</div></div>';
        });

        html += '</div></div>';
    });

    $('#data-container').html(html);
}

function openInputModalNew() {
    $('#input-form')[0].reset();
    $('#input-id, #input-sub-kegiatan-id').val('');
    $('#input-sub-kegiatan-select').val(null).trigger('change');
    var curY = String(new Date().getFullYear());
    document.getElementById('input-tahun-select').value = curY;
    document.getElementById('input-tahun').value = curY;
    document.getElementById('lbl-modal-tahun').textContent = curY;
    document.getElementById('input-sumber').value = 'APBD';
    document.getElementById('lbl-sumber').textContent = 'APBD';
    $('#section-select').removeClass('hidden');
    $('#modal-header').removeClass('from-blue-600 to-blue-700').addClass('from-green-600 to-green-700');
    $('#modal-title').text('Tambah Data Baru');
    $('#modal-subtitle').text('Pilih sub kegiatan dan isi data');
    resetPreview();
    $('#input-modal').removeClass('hidden').addClass('flex');
}

function openInputModal(item) {
    var lap    = (item.laporan && item.laporan[0]) || {};
    var isEdit = !!lap.id;
    $('#section-select').addClass('hidden');
    $('#modal-header').removeClass('from-green-600 to-green-700 from-blue-600 to-blue-700')
        .addClass(isEdit ? 'from-blue-600 to-blue-700' : 'from-green-600 to-green-700');
    $('#modal-title').text(isEdit ? 'Edit Data' : 'Input Data');
    $('#modal-subtitle').text(item.nama_sub_kegiatan);
    $('#input-id').val(lap.id || '');
    $('#input-sub-kegiatan-id').val(item.id);
    var tahunVal = document.getElementById('filter-tahun').value;
    $('#input-tahun').val(tahunVal);
    document.getElementById('input-tahun-select').value = tahunVal;
    document.getElementById('lbl-modal-tahun').textContent = tahunVal;
    $('#input-durasi').val(lap.durasi_pemberian || '');
    $('#input-besaran').val(lap.besaran_manfaat || '');
    $('#input-jenis').val(lap.jenis_bantuan || '');
    $('#input-sasaran').val(lap.jumlah_sasaran || '');
    $('#input-satuan').val(lap.satuan_sasaran || '');
    $('#input-akt-langsung').val(lap.aktivitas_langsung || '');
    $('#input-akt-tidak-langsung').val(lap.aktivitas_tidak_langsung || '');
    $('#input-akt-penunjang').val(lap.aktivitas_penunjang || '');
    $('#input-langsung').val(lap.penerima_langsung || 0);
    $('#input-tidak-langsung').val(lap.penerima_tidak_langsung || 0);
    $('#input-penunjang').val(lap.penerima_penunjang || 0);
    var sumberVal = lap.sumber_pembiayaan || 'APBD';
    $('#input-sumber').val(sumberVal);
    document.getElementById('lbl-sumber').textContent = sumberVal;
    $('#input-sifat').val(lap.sifat_bantuan || '');
    $('#input-lokasi').val(lap.lokasi || '');
    $('#input-alokasi').val(lap.alokasi_anggaran || 0);
    $('#input-real-sem1').val(lap.realisasi_sem1 || 0);
    $('#input-real-sem2').val(lap.realisasi_sem2 || 0);
    calculatePreview();
    $('#input-modal').removeClass('hidden').addClass('flex');
}

function closeModal() { $('#input-modal').removeClass('flex').addClass('hidden'); }

function calculatePreview() {
    var sem1    = parseFloat($('#input-real-sem1').val()) || 0;
    var sem2    = parseFloat($('#input-real-sem2').val()) || 0;
    var alokasi = parseFloat($('#input-alokasi').val())   || 0;
    var total   = sem1 + sem2;
    var persen  = alokasi > 0 ? ((total / alokasi) * 100).toFixed(1) : 0;
    $('#preview-total').text(formatCurrency(total));
    $('#preview-persen').text(persen + '%');
    $('#preview-progress').css('width', Math.min(persen, 100) + '%')
        .removeClass('bg-red-500 bg-yellow-500 bg-green-500')
        .addClass(persen >= 80 ? 'bg-green-500' : (persen >= 50 ? 'bg-yellow-500' : 'bg-red-500'));
}

function resetPreview() {
    $('#preview-total').text('Rp 0');
    $('#preview-persen').text('0%');
    $('#preview-progress').css('width', '0%').removeClass('bg-red-500 bg-yellow-500').addClass('bg-green-500');
}

function saveData() {
    if ($('#section-select').is(':visible') && !$('#input-sub-kegiatan-select').val()) {
        showToast('Pilih Sub Kegiatan dulu', 'error');
        return;
    }
    showLoading();
    $.ajax({
        url: '{{ route("oppkpke.laporan.store") }}',
        method: 'POST',
        data: $('#input-form').serialize(),
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        success: function (res) { hideLoading(); closeModal(); showToast(res.message || 'Berhasil disimpan'); loadData(); },
        error: function (xhr)   {
            hideLoading();
            var err = xhr.responseJSON && xhr.responseJSON.errors;
            showToast(err ? Object.values(err).flat().join(', ') : 'Gagal menyimpan', 'error');
        }
    });
}
</script>
@endpush
