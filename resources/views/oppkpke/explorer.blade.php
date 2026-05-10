@extends('layouts.oppkpke')

@section('title', 'Explorer Data')
@section('page-title', 'Explorer Data OPPKPKE')
@section('page-subtitle', 'Telusuri data dengan mudah menggunakan filter hierarkis')

@section('content')

{{-- ── Mobile filter toggle ───────────────────────────────── --}}
<button onclick="toggleFilterPanel()"
        class="lg:hidden w-full mb-3 flex items-center justify-between bg-white rounded-xl border shadow-sm px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
    <span class="flex items-center gap-2">
        <i class="fas fa-filter text-blue-500"></i> Filter Data
    </span>
    <i class="fas fa-chevron-down text-gray-400 transition-transform duration-200" id="filter-chevron"></i>
</button>

<div class="flex flex-col lg:flex-row gap-4 lg:gap-6">

    {{-- ── Filter Sidebar ───────────────────────────────────── --}}
    <div id="filter-panel"
         class="filter-panel-mobile collapsed lg:!max-h-none lg:!opacity-100 lg:block lg:w-80 lg:flex-shrink-0">
        <div class="bg-white rounded-xl shadow-sm border p-4 md:p-6 lg:sticky lg:top-24">
            <h3 class="font-semibold text-gray-800 mb-3 text-sm md:text-base">
                <i class="fas fa-filter mr-2 text-blue-500"></i> Filter Data
            </h3>

            <form id="filter-form" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
                    <div class="relative">
                        <input type="text" name="search" id="filter-search"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Cari program, kegiatan..."
                               class="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                    </div>
                </div>

@php
    $exInitS  = $filters['strategi_id']         ?? '';
    $exInitPd = $filters['perangkat_daerah_id'] ?? '';
    $exInitPr = $filters['program_id']           ?? '';
    $exInitKg = $filters['kegiatan_id']          ?? '';
    $exInitTh = $filters['tahun']                ?? date('Y');
    $exLblS   = collect($filterOptions['strategi']       ?? [])->firstWhere('id', $exInitS)?->nama  ?? '-- Semua Strategi --';
    $exLblPd  = collect($filterOptions['perangkat_daerah'] ?? [])->firstWhere('id', $exInitPd)?->nama ?? '-- Semua Perangkat --';
    $exLblPr  = collect($filterOptions['programs']       ?? [])->firstWhere('id', $exInitPr)?->nama_program ?? '-- Semua Program --';
    $exLblKg  = collect($filterOptions['kegiatan']       ?? [])->firstWhere('id', $exInitKg)?->nama_kegiatan ?? '-- Semua Kegiatan --';
@endphp
{{-- Strategi --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Strategi OPPKPKE</label>
    <input type="hidden" name="strategi_id" id="filter-strategi" value="{{ $exInitS }}">
    <button type="button" onclick="exOpenStrategi()"
            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
        <i class="fas fa-sitemap text-gray-400 text-xs flex-shrink-0"></i>
        <span id="lbl-strategi" class="flex-1 truncate" style="color:{{ $exInitS ? '#374151' : '#9ca3af' }}">{{ $exLblS }}</span>
        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
    </button>
</div>

{{-- Perangkat Daerah --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Perangkat Daerah</label>
    <input type="hidden" name="perangkat_daerah_id" id="filter-perangkat" value="{{ $exInitPd }}">
    <button type="button" onclick="exOpenPerangkat()"
            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
        <i class="fas fa-building text-gray-400 text-xs flex-shrink-0"></i>
        <span id="lbl-perangkat" class="flex-1 truncate" style="color:{{ $exInitPd ? '#374151' : '#9ca3af' }}">{{ $exLblPd }}</span>
        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
    </button>
</div>

{{-- Program --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Program</label>
    <input type="hidden" name="program_id" id="filter-program" value="{{ $exInitPr }}">
    <button type="button" onclick="exOpenProgram()"
            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
        <i class="fas fa-folder text-gray-400 text-xs flex-shrink-0"></i>
        <span id="lbl-program" class="flex-1 truncate" style="color:{{ $exInitPr ? '#374151' : '#9ca3af' }}">{{ Str::limit($exLblPr, 35) }}</span>
        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
    </button>
</div>

{{-- Kegiatan --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Kegiatan</label>
    <input type="hidden" name="kegiatan_id" id="filter-kegiatan" value="{{ $exInitKg }}">
    <button type="button" onclick="exOpenKegiatan()"
            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
        <i class="fas fa-clipboard-list text-gray-400 text-xs flex-shrink-0"></i>
        <span id="lbl-kegiatan" class="flex-1 truncate" style="color:{{ $exInitKg ? '#374151' : '#9ca3af' }}">{{ Str::limit($exLblKg, 35) }}</span>
        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
    </button>
</div>

{{-- Tahun --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
    <input type="hidden" name="tahun" id="filter-tahun" value="{{ $exInitTh }}">
    <button type="button" onclick="exOpenTahun()"
            class="w-full flex items-center gap-2 border border-gray-300 rounded-lg px-3 py-2 bg-white hover:bg-gray-50 transition text-left text-sm">
        <i class="fas fa-calendar text-gray-400 text-xs flex-shrink-0"></i>
        <span id="lbl-tahun" class="flex-1 font-medium text-gray-700">{{ $exInitTh }}</span>
        <i class="fas fa-chevron-down text-gray-400 text-xs flex-shrink-0"></i>
    </button>
</div>

                <div class="flex gap-2 pt-2 border-t">
                    <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-search mr-1"></i> Cari
                    </button>
                    <button type="button" id="btn-reset" class="px-4 py-2 border rounded-lg hover:bg-gray-50 transition text-sm">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Main Content ──────────────────────────────────────── --}}
    <div class="flex-1 min-w-0">
        <div class="bg-white rounded-xl shadow-sm border p-3 md:p-4 mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <span id="result-count" class="font-semibold text-gray-800">0</span>
                <span class="text-gray-500 text-sm"> data ditemukan</span>
            </div>
            <div class="flex gap-2">
                <button id="btn-expand-all"   class="px-2 md:px-3 py-1.5 text-xs border rounded hover:bg-gray-50">
                    <i class="fas fa-expand-alt mr-1"></i><span class="hidden sm:inline">Expand All</span>
                </button>
                <button id="btn-collapse-all" class="px-2 md:px-3 py-1.5 text-xs border rounded hover:bg-gray-50">
                    <i class="fas fa-compress-alt mr-1"></i><span class="hidden sm:inline">Collapse All</span>
                </button>
            </div>
        </div>

        <div id="data-container" class="space-y-4">
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-search text-4xl mb-3 text-gray-300"></i>
                <p>Gunakan filter untuk mencari data</p>
            </div>
        </div>
    </div>
</div>

{{-- ── Edit Modal ──────────────────────────────────────────── --}}
<div id="edit-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-3 md:p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="bg-white border-b px-4 md:px-6 py-3 md:py-4 flex justify-between items-center flex-shrink-0">
            <h3 class="font-semibold text-base md:text-lg truncate pr-4" id="modal-title">Edit Data Realisasi</h3>
            <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700 flex-shrink-0">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="edit-form" class="p-4 md:p-6 overflow-y-auto">
            <input type="hidden" name="sub_kegiatan_id" id="edit-sub-kegiatan-id">
            <input type="hidden" name="tahun" id="edit-tahun">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durasi Pemberian</label>
                    <input type="text" name="durasi_pemberian" id="edit-durasi"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Besaran Manfaat</label>
                    <input type="text" name="besaran_manfaat" id="edit-besaran"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Bantuan</label>
                    <input type="text" name="jenis_bantuan" id="edit-jenis"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Sasaran</label>
                    <input type="number" name="jumlah_sasaran" id="edit-sasaran"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Penerima Langsung</label>
                    <input type="number" name="penerima_langsung" id="edit-langsung" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Penerima Tidak Langsung</label>
                    <input type="number" name="penerima_tidak_langsung" id="edit-tidak-langsung" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Penerima Penunjang</label>
                    <input type="number" name="penerima_penunjang" id="edit-penunjang" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sumber Pembiayaan</label>
                    <select name="sumber_pembiayaan" id="edit-sumber"
                            class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                        <option>APBD</option><option>APBN</option><option>DAK</option><option>Dana Desa</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sifat Bantuan</label>
                    <input type="text" name="sifat_bantuan" id="edit-sifat"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lokasi</label>
                    <input type="text" name="lokasi" id="edit-lokasi"
                           class="w-full border rounded-lg text-sm p-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="col-span-1 sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alokasi Anggaran (Rp)</label>
                    <input type="number" name="alokasi_anggaran" id="edit-alokasi" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 font-mono focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Realisasi Semester 1</label>
                    <input type="number" name="realisasi_sem1" id="edit-real-sem1" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 font-mono focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Realisasi Semester 2</label>
                    <input type="number" name="realisasi_sem2" id="edit-real-sem2" step="0.01"
                           class="w-full border rounded-lg text-sm p-2 font-mono focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 md:gap-3 mt-5 pt-4 border-t">
                <button type="button" onclick="closeModal()"
                        class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
                <button type="submit"
                        class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition">
                    <i class="fas fa-save mr-1"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Picker item arrays (initialised from PHP) ──────────────
var _exStrategiItems = [{ value: '', label: '-- Semua Strategi --' }].concat(
    @json(collect($filterOptions['strategi'] ?? [])->map(fn($s) => ['value' => $s->id, 'label' => $s->nama])->values())
);
var _exPdItems = [{ value: '', label: '-- Semua Perangkat --' }].concat(
    @json(collect($filterOptions['perangkat_daerah'] ?? [])->map(fn($pd) => ['value' => $pd->id, 'label' => $pd->nama])->values())
);
var _exProgramItems = [{ value: '', label: '-- Semua Program --' }].concat(
    @json(collect($filterOptions['programs'] ?? [])->map(fn($p) => ['value' => $p->id, 'label' => $p->nama_program])->values())
);
var _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }].concat(
    @json(collect($filterOptions['kegiatan'] ?? [])->map(fn($k) => ['value' => $k->id, 'label' => $k->nama_kegiatan])->values())
);
var _exTahunItems = (function() {
    var a = [], cur = new Date().getFullYear();
    for (var y = cur + 1; y >= cur - 5; y--) a.push({ value: String(y), label: String(y) });
    return a;
})();

// Sets a picker field's hidden input + button label
function exSetBtn(field, value, label) {
    document.getElementById('filter-' + field).value = value != null ? value : '';
    var lbl = document.getElementById('lbl-' + field);
    lbl.textContent  = label;
    lbl.style.color  = (value !== '' && value != null) ? '#374151' : '#9ca3af';
}

// Individual openers
function exOpenStrategi() {
    gpOpen({
        title: 'Pilih Strategi OPPKPKE',
        targetId: 'filter-strategi',
        items: _exStrategiItems,
        showSearch: false,
        onSelect: function (item) {
            exSetBtn('strategi', item.value, item.label);
            exSetBtn('perangkat', '', '-- Semua Perangkat --');
            exSetBtn('program',   '', '-- Semua Program --');
            exSetBtn('kegiatan',  '', '-- Semua Kegiatan --');
            _exPdItems       = [{ value: '', label: '-- Semua Perangkat --' }];
            _exProgramItems  = [{ value: '', label: '-- Semua Program --' }];
            _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }];
            if (item.value) {
                $.get('{{ route("oppkpke.options.perangkat-daerah") }}', { strategi_id: item.value }, function (d) {
                    _exPdItems = [{ value: '', label: '-- Semua Perangkat --' }].concat(d.map(function (i) { return { value: i.id, label: i.nama }; }));
                });
                $.get('{{ route("oppkpke.options.programs") }}', { strategi_id: item.value }, function (d) {
                    _exProgramItems = [{ value: '', label: '-- Semua Program --' }].concat(d.map(function (i) { return { value: i.id, label: i.nama_program }; }));
                });
            }
        }
    });
}

function exOpenPerangkat() {
    gpOpen({
        title: 'Pilih Perangkat Daerah',
        targetId: 'filter-perangkat',
        items: _exPdItems,
        showSearch: _exPdItems.length > 6,
        onSelect: function (item) {
            exSetBtn('perangkat', item.value, item.label);
            exSetBtn('program',  '', '-- Semua Program --');
            exSetBtn('kegiatan', '', '-- Semua Kegiatan --');
            _exProgramItems  = [{ value: '', label: '-- Semua Program --' }];
            _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }];
            var sId = document.getElementById('filter-strategi').value;
            if (item.value || sId) {
                $.get('{{ route("oppkpke.options.programs") }}', { strategi_id: sId, perangkat_daerah_id: item.value }, function (d) {
                    _exProgramItems = [{ value: '', label: '-- Semua Program --' }].concat(d.map(function (i) { return { value: i.id, label: i.nama_program }; }));
                });
            }
        }
    });
}

function exOpenProgram() {
    gpOpen({
        title: 'Pilih Program',
        targetId: 'filter-program',
        items: _exProgramItems,
        showSearch: _exProgramItems.length > 6,
        onSelect: function (item) {
            exSetBtn('program', item.value, item.label);
            exSetBtn('kegiatan', '', '-- Semua Kegiatan --');
            _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }];
            if (item.value) {
                $.get('{{ route("oppkpke.options.kegiatan") }}', { program_id: item.value }, function (d) {
                    _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }].concat(d.map(function (i) { return { value: i.id, label: i.nama_kegiatan }; }));
                });
            }
        }
    });
}

function exOpenKegiatan() {
    gpOpen({
        title: 'Pilih Kegiatan',
        targetId: 'filter-kegiatan',
        items: _exKegiatanItems,
        showSearch: _exKegiatanItems.length > 6,
        onSelect: function (item) { exSetBtn('kegiatan', item.value, item.label); }
    });
}

function exOpenTahun() {
    gpOpen({
        title: 'Pilih Tahun',
        targetId: 'filter-tahun',
        items: _exTahunItems,
        showSearch: false,
        onSelect: function (item) {
            document.getElementById('filter-tahun').value = item.value;
            var lbl = document.getElementById('lbl-tahun');
            lbl.textContent = item.label;
            lbl.style.color = '#374151';
        }
    });
}

$(function () {
    loadData();

    $('#filter-form').on('submit', function (e) { e.preventDefault(); loadData(); });
    $('#btn-reset').on('click', function () {
        exSetBtn('strategi',  '', '-- Semua Strategi --');
        exSetBtn('perangkat', '', '-- Semua Perangkat --');
        exSetBtn('program',   '', '-- Semua Program --');
        exSetBtn('kegiatan',  '', '-- Semua Kegiatan --');
        var curY = String(new Date().getFullYear());
        document.getElementById('filter-tahun').value = curY;
        document.getElementById('lbl-tahun').textContent = curY;
        document.getElementById('filter-search').value = '';
        _exPdItems       = [{ value: '', label: '-- Semua Perangkat --' }].concat(
            @json(collect($filterOptions['perangkat_daerah'] ?? [])->map(fn($pd) => ['value' => $pd->id, 'label' => $pd->nama])->values())
        );
        _exProgramItems  = [{ value: '', label: '-- Semua Program --' }].concat(
            @json(collect($filterOptions['programs'] ?? [])->map(fn($p) => ['value' => $p->id, 'label' => $p->nama_program])->values())
        );
        _exKegiatanItems = [{ value: '', label: '-- Semua Kegiatan --' }].concat(
            @json(collect($filterOptions['kegiatan'] ?? [])->map(fn($k) => ['value' => $k->id, 'label' => $k->nama_kegiatan])->values())
        );
        loadData();
    });
    $('#btn-expand-all').on('click', function ()   { $('.collapse-content').slideDown(200); });
    $('#btn-collapse-all').on('click', function ()  { $('.collapse-content').slideUp(200); });
    $('#edit-form').on('submit', function (e) { e.preventDefault(); saveData(); });
});

function loadData() {
    showLoading();
    $.get('{{ route("oppkpke.explorer.data") }}', $('#filter-form').serialize())
        .done(function (res) { hideLoading(); renderData(res.data); $('#result-count').text(res.data.length); })
        .fail(function ()    { hideLoading(); showToast('Gagal memuat data', 'error'); });
}

function getColorClasses(strategiId) {
    var colors = {
        1: { bg: 'bg-blue-500',   bgLight: 'bg-blue-50',   bgHover: 'hover:bg-blue-100',   text: 'text-blue-600',   progress: 'bg-blue-500' },
        2: { bg: 'bg-green-500',  bgLight: 'bg-green-50',  bgHover: 'hover:bg-green-100',  text: 'text-green-600',  progress: 'bg-green-500' },
        3: { bg: 'bg-orange-500', bgLight: 'bg-orange-50', bgHover: 'hover:bg-orange-100', text: 'text-orange-600', progress: 'bg-orange-500' }
    };
    return colors[strategiId] || colors[1];
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function renderData(data) {
    if (!data.length) {
        $('#data-container').html('<div class="text-center py-12 text-gray-500"><i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i><p>Tidak ada data ditemukan</p></div>');
        return;
    }

    var grouped = {};
    data.forEach(function (item) {
        var strategi   = item.kegiatan?.program?.strategi?.nama            || 'Unknown';
        var strategiId = item.kegiatan?.program?.strategi?.id              || 1;
        var perangkat  = item.kegiatan?.program?.perangkat_daerah?.nama    || 'Unknown';
        var program    = item.kegiatan?.program?.nama_program              || 'Unknown';
        var kegiatan   = item.kegiatan?.nama_kegiatan                      || 'Unknown';
        var key        = strategi + '|||' + perangkat + '|||' + program + '|||' + kegiatan;

        if (!grouped[key]) {
            grouped[key] = { strategi, strategiId, perangkat, program, kegiatan, items: [] };
        }
        grouped[key].items.push(item);
    });

    var html = '';
    var idx  = 0;

    Object.keys(grouped).forEach(function (key) {
        var group    = grouped[key];
        idx++;
        var totalAlokasi = 0, totalSem1 = 0, totalSem2 = 0, totalRealisasi = 0;
        group.items.forEach(function (item) {
            var lap = (item.laporan && item.laporan[0]) || {};
            totalAlokasi   += parseFloat(lap.alokasi_anggaran) || 0;
            totalSem1      += parseFloat(lap.realisasi_sem1)   || 0;
            totalSem2      += parseFloat(lap.realisasi_sem2)   || 0;
            totalRealisasi += parseFloat(lap.realisasi_total)  || 0;
        });
        var persen = totalAlokasi > 0 ? ((totalRealisasi / totalAlokasi) * 100).toFixed(1) : 0;
        var colors = getColorClasses(group.strategiId);

        html += '<div class="bg-white rounded-xl shadow-sm border overflow-hidden">';
        html += '<div class="px-4 md:px-6 py-3 md:py-4 ' + colors.bgLight + ' border-b cursor-pointer ' + colors.bgHover + ' transition" onclick="$(this).next(\'.collapse-content\').slideToggle(200)">';
        html += '<div class="flex items-start justify-between gap-3">';
        html += '<div class="flex items-start gap-3 min-w-0">';
        html += '<div class="w-9 h-9 md:w-10 md:h-10 ' + colors.bg + ' rounded-lg flex items-center justify-center text-white font-bold flex-shrink-0 text-sm">' + idx + '</div>';
        html += '<div class="min-w-0">';
        html += '<div class="text-xs ' + colors.text + ' font-medium">' + escapeHtml(group.strategi) + '</div>';
        html += '<div class="font-semibold text-gray-800 text-sm md:text-base">' + escapeHtml(group.perangkat) + '</div>';
        html += '<div class="text-xs md:text-sm text-gray-500 truncate">' + escapeHtml(group.program) + '</div>';
        html += '</div></div>';
        html += '<div class="text-right flex-shrink-0">';
        html += '<div class="text-xs text-gray-500 mb-0.5">Alokasi: <span class="font-medium text-blue-700">' + formatCurrency(totalAlokasi) + '</span></div>';
        html += '<div class="text-xs text-gray-500 mb-0.5">Sem.1: <span class="font-medium text-purple-600">' + formatCurrency(totalSem1) + '</span> · Sem.2: <span class="font-medium text-indigo-600">' + formatCurrency(totalSem2) + '</span></div>';
        html += '<div class="font-bold text-green-600 text-sm">' + persen + '% <span class="text-xs font-normal text-gray-500">(' + formatCurrency(totalRealisasi) + ')</span></div>';
        html += '<div class="w-28 bg-gray-200 rounded-full h-1.5 mt-1 ml-auto">';
        html += '<div class="' + colors.progress + ' h-1.5 rounded-full" style="width:' + Math.min(persen, 100) + '%"></div>';
        html += '</div></div></div></div>';

        html += '<div class="collapse-content" style="display:none;">';
        html += '<div class="px-4 md:px-6 py-2 bg-gray-50 border-b">';
        html += '<span class="text-xs font-medium text-gray-500 uppercase">Kegiatan:</span> ';
        html += '<span class="text-sm text-gray-600">' + escapeHtml(group.kegiatan) + '</span>';
        html += '</div><div class="divide-y">';

        group.items.forEach(function (item) {
            var lap      = (item.laporan && item.laporan[0]) || {};
            var itemData = JSON.stringify(item).replace(/'/g, "\\'").replace(/"/g, '&quot;');
            var itemSem1 = parseFloat(lap.realisasi_sem1) || 0;
            var itemSem2 = parseFloat(lap.realisasi_sem2) || 0;

            html += '<div class="px-4 md:px-6 py-3 hover:bg-gray-50 transition">';
            html += '<div class="flex items-start justify-between gap-3">';
            html += '<div class="flex-1 min-w-0">';
            html += '<div class="font-medium text-gray-800 text-sm">' + escapeHtml(item.nama_sub_kegiatan) + '</div>';
            html += '<div class="flex flex-wrap gap-3 mt-1.5 text-xs text-gray-500">';
            html += '<span><i class="fas fa-money-bill-wave mr-1"></i>Alokasi: ' + formatCurrency(lap.alokasi_anggaran || 0) + '</span>';
            html += '<span><i class="fas fa-1 mr-1 text-purple-500"></i>Sem.1: ' + formatCurrency(itemSem1) + '</span>';
            html += '<span><i class="fas fa-2 mr-1 text-indigo-500"></i>Sem.2: ' + formatCurrency(itemSem2) + '</span>';
            html += '<span class="font-semibold text-green-600"><i class="fas fa-chart-line mr-1"></i>Total: ' + formatCurrency(lap.realisasi_total || 0) + '</span>';
            html += '<span><i class="fas fa-users mr-1"></i>Sasaran: ' + (lap.jumlah_sasaran || '-') + '</span>';
            html += '</div></div>';
            html += '<button onclick=\'openEditModal(' + itemData + ')\' class="flex-shrink-0 px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition">';
            html += '<i class="fas fa-edit mr-1"></i><span class="hidden sm:inline">Edit</span>';
            html += '</button></div></div>';
        });

        html += '</div></div></div>';
    });

    $('#data-container').html(html);
}

function openEditModal(item) {
    var laporan = (item.laporan && item.laporan[0]) || {};
    $('#modal-title').text('Edit: ' + item.nama_sub_kegiatan);
    $('#edit-sub-kegiatan-id').val(item.id);
    $('#edit-tahun').val($('#filter-tahun').val());
    $('#edit-durasi').val(laporan.durasi_pemberian || '');
    $('#edit-besaran').val(laporan.besaran_manfaat || '');
    $('#edit-jenis').val(laporan.jenis_bantuan || '');
    $('#edit-sasaran').val(laporan.jumlah_sasaran || '');
    $('#edit-langsung').val(laporan.penerima_langsung || 0);
    $('#edit-tidak-langsung').val(laporan.penerima_tidak_langsung || 0);
    $('#edit-penunjang').val(laporan.penerima_penunjang || 0);
    $('#edit-sumber').val(laporan.sumber_pembiayaan || 'APBD');
    $('#edit-sifat').val(laporan.sifat_bantuan || '');
    $('#edit-lokasi').val(laporan.lokasi || '');
    $('#edit-alokasi').val(laporan.alokasi_anggaran || 0);
    $('#edit-real-sem1').val(laporan.realisasi_sem1 || 0);
    $('#edit-real-sem2').val(laporan.realisasi_sem2 || 0);
    $('#edit-modal').removeClass('hidden').addClass('flex');
}

function closeModal() { $('#edit-modal').removeClass('flex').addClass('hidden'); }

function saveData() {
    showLoading();
    $.ajax({
        url: '{{ route("oppkpke.laporan.store") }}',
        method: 'POST',
        data: $('#edit-form').serialize(),
        success: function (res) { hideLoading(); closeModal(); showToast(res.message); loadData(); },
        error: function (xhr) {
            hideLoading();
            var errors = xhr.responseJSON && xhr.responseJSON.errors;
            showToast(errors ? Object.values(errors).flat().join(', ') : 'Gagal menyimpan data', 'error');
        }
    });
}
</script>
@endpush
