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
                @if(auth()->user()->isMaster() || auth()->user()->isItTeam() || auth()->user()->perangkat_daerah_id)
                <button type="button" onclick="wzOpen()"
                        class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs md:text-sm hover:bg-indigo-700 transition"
                        title="Buat program / kegiatan / sub kegiatan yang belum ada">
                    <i class="fas fa-sitemap mr-1"></i> Kegiatan Baru
                </button>
                @endif
                <button type="button" onclick="openInputModalNew()"
                        class="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs md:text-sm hover:bg-green-700 transition"
                        title="Pilih sub kegiatan lalu isi anggaran & realisasinya">
                    <i class="fas fa-pen-to-square mr-1"></i> Isi Laporan
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

{{-- ══════════════════════════════════════════════════════════════════
     WIZARD: Tambah Kegiatan Baru (Program → Kegiatan → Sub Kegiatan)
     • Operator Daerah → terkunci ke perangkat daerah sendiri (4 langkah).
     • Admin Master / Tim IT → pilih/buat perangkat daerah dulu (5 langkah).
══════════════════════════════════════════════════════════════════ --}}
@php
    $wzUser    = auth()->user();
    $wzIsAdmin = $wzUser->isMaster() || $wzUser->isItTeam();
    $wzCanUse  = $wzIsAdmin || $wzUser->perangkat_daerah_id;
    $wzPdNama  = optional($wzUser->perangkatDaerah)->nama ?? 'Perangkat Daerah Anda';
@endphp
@if($wzCanUse)
<div id="wz-modal" class="fixed inset-0 bg-black/50 z-[60] hidden items-center justify-center p-3 md:p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[94vh] overflow-hidden flex flex-col shadow-2xl">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-indigo-600 to-indigo-700 px-4 py-3 flex justify-between items-start flex-shrink-0">
            <div class="min-w-0">
                <h3 class="font-semibold text-white text-sm md:text-base"><i class="fas fa-sitemap mr-1.5"></i> Tambah Kegiatan Baru</h3>
                <p class="text-xs text-white/70 mt-0.5 truncate">
                    @if($wzIsAdmin) Mode Admin — pilih perangkat daerah tujuan @else Untuk: {{ $wzPdNama }} @endif
                </p>
            </div>
            <button type="button" onclick="wzClose()" class="text-white/70 hover:text-white ml-2 flex-shrink-0"><i class="fas fa-times text-lg"></i></button>
        </div>

        {{-- Stepper (dibangun dinamis oleh JS sesuai peran) --}}
        <div class="px-4 md:px-6 pt-4 pb-2 border-b flex-shrink-0">
            <div id="wz-stepper" class="flex items-center justify-between"></div>
        </div>

        {{-- Body --}}
        <div class="overflow-y-auto flex-1 p-4 md:p-6">

            {{-- ── PANDUAN RANTAI (role-aware) ─────────────────────── --}}
            <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50/60 overflow-hidden">
                <button type="button" onclick="wzToggleGuide()" class="w-full flex items-center justify-between px-4 py-2.5 text-left">
                    <span class="text-xs md:text-sm font-semibold text-indigo-800 flex items-center gap-2">
                        <i class="fas fa-book-open"></i> Panduan Pengisian — Rantai Data
                    </span>
                    <i id="wz-guide-chevron" class="fas fa-chevron-down text-indigo-500 transition-transform" style="transform:rotate(180deg)"></i>
                </button>
                <div id="wz-guide-body" class="px-4 pb-4 space-y-3">
                    <p class="text-xs text-indigo-900/80">Anda membangun data dari <strong>besar ke kecil</strong>. Setiap tingkat menaungi tingkat di bawahnya:</p>

                    {{-- Rantai visual --}}
                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-medium">
                        @if($wzIsAdmin)
                        <span class="px-2 py-1 rounded-full bg-amber-100 text-amber-800"><i class="fas fa-building mr-1"></i>Perangkat Daerah</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        @endif
                        <span class="px-2 py-1 rounded-full bg-blue-100 text-blue-800"><i class="fas fa-folder mr-1"></i>Program</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span class="px-2 py-1 rounded-full bg-purple-100 text-purple-800"><i class="fas fa-list mr-1"></i>Kegiatan</span>
                        <i class="fas fa-arrow-right text-gray-400"></i>
                        <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-800"><i class="fas fa-check mr-1"></i>Sub Kegiatan</span>
                    </div>

                    {{-- Definisi tiap mata rantai --}}
                    <ol class="text-xs text-indigo-900/90 space-y-1.5 list-decimal ml-4">
                        @if($wzIsAdmin)
                        <li><strong>Perangkat Daerah</strong> — instansi pemilik data (mis. Dinas Sosial). Pilih yang sudah ada; buat baru hanya bila instansi benar-benar belum terdaftar.</li>
                        @endif
                        <li><strong>Program</strong> — payung besar kegiatan (mis. “Program Pemberdayaan Sosial”), punya <em>kode</em> &amp; <em>nama</em>.</li>
                        <li><strong>Kegiatan</strong> — rincian dari program (mis. “Pemberdayaan Sosial KAT”).</li>
                        <li><strong>Sub Kegiatan</strong> — unit terkecil yang <strong>diisi anggaran &amp; realisasinya</strong>; inilah yang akhirnya muncul di daftar Input Data.</li>
                    </ol>

                    {{-- Aturan main --}}
                    <div class="text-[11px] text-indigo-900/80 space-y-1 border-t border-indigo-200 pt-2">
                        <p><i class="fas fa-circle-info text-indigo-500 mr-1"></i><strong>Pakai yang sudah ada</strong> bila tingkat itu sudah tercatat — cukup pilih dari daftar. <strong>Buat baru</strong> hanya bila belum ada.</p>
                        <p><i class="fas fa-diagram-project text-indigo-500 mr-1"></i>Bila tingkat atas dibuat baru, tingkat di bawahnya otomatis ikut baru.</p>
                        <p><i class="fas fa-copy text-indigo-500 mr-1"></i>Sistem menolak nama yang mirip agar tidak terjadi <strong>duplikat</strong>.</p>
                        @if($wzIsAdmin)
                        <p><i class="fas fa-user-shield text-indigo-500 mr-1"></i><strong>Mode Admin:</strong> data masuk ke <strong>perangkat daerah yang Anda pilih</strong> di langkah pertama — pastikan benar sebelum menyimpan.</p>
                        @else
                        <p><i class="fas fa-lock text-indigo-500 mr-1"></i>Semua data otomatis masuk ke <strong>{{ $wzPdNama }}</strong> (perangkat daerah Anda) — tidak bisa ke PD lain.</p>
                        @endif
                    </div>
                </div>
            </div>

            @if($wzIsAdmin)
            {{-- ── STEP: PERANGKAT DAERAH (admin) ──────────────────── --}}
            <div id="wz-step-pd" class="wz-step space-y-4 hidden">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800 flex gap-2">
                    <i class="fas fa-user-shield mt-0.5"></i>
                    <div><strong>Mode Admin.</strong> Data yang Anda buat akan dimasukkan ke <strong>perangkat daerah yang dipilih di sini</strong>. Pastikan benar — operator PD tersebut akan melihat & mengisinya.</div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="wz-radio-card" data-for="pd">
                        <input type="radio" name="wz_pd_mode" value="existing" class="hidden" onchange="wzSetPdMode('existing')" checked>
                        <i class="fas fa-building"></i><span>Pilih perangkat daerah yang ada</span>
                    </label>
                    <label class="wz-radio-card" data-for="pd">
                        <input type="radio" name="wz_pd_mode" value="new" class="hidden" onchange="wzSetPdMode('new')">
                        <i class="fas fa-plus"></i><span>Buat perangkat daerah baru</span>
                    </label>
                </div>
                {{-- existing --}}
                <div id="wz-pd-existing">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Perangkat Daerah</label>
                    <select id="wz-pd-id" class="w-full border rounded-lg text-sm p-2.5 bg-white"><option value="">-- Memuat... --</option></select>
                </div>
                {{-- new --}}
                <div id="wz-pd-new" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Perangkat Daerah <span class="text-red-500">*</span></label>
                        <input id="wz-pd-nama" type="text" maxlength="255" placeholder="mis. Dinas Sosial" class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Singkatan</label>
                        <input id="wz-pd-singkatan" type="text" maxlength="50" placeholder="opsional" class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                    <div class="sm:col-span-3">
                        <p class="text-[11px] text-amber-600"><i class="fas fa-triangle-exclamation mr-1"></i>Buat PD baru hanya bila benar-benar belum ada. Sistem menolak nama yang mirip untuk mencegah duplikat.</p>
                    </div>
                </div>
            </div>
            @endif

            {{-- ── STEP 1: PROGRAM ─────────────────────────────────── --}}
            <div id="wz-step-1" class="wz-step space-y-4 hidden">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-xs text-indigo-800 flex gap-2">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <div><strong>Program</strong> adalah payung besar kegiatan (mis. <em>“Program Pemberdayaan Sosial”</em>). Pilih program yang sudah ada bila kegiatan Anda masih di bawahnya, atau buat baru bila belum tercatat.</div>
                </div>

                <div id="wz-prog-mode-wrap" class="grid grid-cols-2 gap-2">
                    <label class="wz-radio-card" data-for="program">
                        <input type="radio" name="wz_program_mode" value="existing" class="hidden" onchange="wzSetProgramMode('existing')" checked>
                        <i class="fas fa-list-check"></i><span>Gunakan program yang sudah ada</span>
                    </label>
                    <label class="wz-radio-card" data-for="program">
                        <input type="radio" name="wz_program_mode" value="new" class="hidden" onchange="wzSetProgramMode('new')">
                        <i class="fas fa-plus"></i><span>Buat program baru</span>
                    </label>
                </div>
                <p id="wz-prog-forcenew" class="hidden text-[11px] text-amber-600"><i class="fas fa-triangle-exclamation mr-1"></i>Karena perangkat daerahnya baru, program juga harus dibuat baru.</p>

                {{-- existing --}}
                <div id="wz-prog-existing">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Pilih Program</label>
                    <select id="wz-program-id" class="w-full border rounded-lg text-sm p-2.5 bg-white"><option value="">-- Memuat... --</option></select>
                </div>

                {{-- new --}}
                <div id="wz-prog-new" class="hidden space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Strategi <span class="text-red-500">*</span></label>
                        <select id="wz-strategi-id" class="w-full border rounded-lg text-sm p-2.5 bg-white"><option value="">-- Memuat... --</option></select>
                        <p class="text-[11px] text-gray-400 mt-1">Pilih strategi OPPKPKE yang menaungi program ini.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Program <span class="text-red-500">*</span></label>
                            <input id="wz-kode-program" type="text" maxlength="50" placeholder="mis. 1.06.05" class="w-full border rounded-lg text-sm p-2.5">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Program <span class="text-red-500">*</span></label>
                            <input id="wz-nama-program" type="text" maxlength="500" placeholder="mis. Program Pemberdayaan Sosial" class="w-full border rounded-lg text-sm p-2.5">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── STEP 2: KEGIATAN ────────────────────────────────── --}}
            <div id="wz-step-2" class="wz-step space-y-4 hidden">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-xs text-indigo-800 flex gap-2">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <div><strong>Kegiatan</strong> adalah rincian dari program (mis. <em>“Pemberdayaan Sosial Komunitas Adat Terpencil”</em>).</div>
                </div>

                <div id="wz-keg-mode-wrap" class="grid grid-cols-2 gap-2">
                    <label class="wz-radio-card" data-for="kegiatan">
                        <input type="radio" name="wz_kegiatan_mode" value="existing" class="hidden" onchange="wzSetKegiatanMode('existing')" checked>
                        <i class="fas fa-list-check"></i><span>Gunakan kegiatan yang sudah ada</span>
                    </label>
                    <label class="wz-radio-card" data-for="kegiatan">
                        <input type="radio" name="wz_kegiatan_mode" value="new" class="hidden" onchange="wzSetKegiatanMode('new')">
                        <i class="fas fa-plus"></i><span>Buat kegiatan baru</span>
                    </label>
                </div>
                <p id="wz-keg-forcenew" class="hidden text-[11px] text-amber-600"><i class="fas fa-triangle-exclamation mr-1"></i>Karena program-nya baru, kegiatan juga harus dibuat baru.</p>

                {{-- existing --}}
                <div id="wz-keg-existing">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Pilih Kegiatan</label>
                    <select id="wz-kegiatan-id" class="w-full border rounded-lg text-sm p-2.5 bg-white"><option value="">-- Pilih program dulu --</option></select>
                </div>

                {{-- new --}}
                <div id="wz-keg-new" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Kegiatan</label>
                        <input id="wz-kode-kegiatan" type="text" maxlength="100" placeholder="opsional" class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Kegiatan <span class="text-red-500">*</span></label>
                        <input id="wz-nama-kegiatan" type="text" maxlength="500" placeholder="mis. Pemberdayaan Sosial KAT" class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                </div>
            </div>

            {{-- ── STEP 3: SUB KEGIATAN ────────────────────────────── --}}
            <div id="wz-step-3" class="wz-step space-y-4 hidden">
                <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-3 text-xs text-indigo-800 flex gap-2">
                    <i class="fas fa-circle-info mt-0.5"></i>
                    <div><strong>Sub Kegiatan</strong> adalah unit terkecil yang nanti Anda isi anggaran &amp; realisasinya (mis. <em>“Fasilitasi Bantuan Pengembangan Ekonomi Masyarakat”</em>).</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Kode Sub Kegiatan</label>
                        <input id="wz-kode-sub" type="text" maxlength="100" placeholder="opsional" class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama Sub Kegiatan <span class="text-red-500">*</span></label>
                        <input id="wz-nama-sub" type="text" maxlength="500" placeholder="mis. Fasilitasi Bantuan ..." class="w-full border rounded-lg text-sm p-2.5">
                    </div>
                </div>
            </div>

            {{-- ── STEP 4: TINJAU ──────────────────────────────────── --}}
            <div id="wz-step-4" class="wz-step space-y-4 hidden">
                <p class="text-sm text-gray-600">Periksa kembali sebelum menyimpan. Data akan dibuat di bawah <strong>{{ $wzPdNama }}</strong>.</p>
                <div id="wz-review" class="bg-gray-50 rounded-lg border p-4 space-y-3 text-sm"></div>
                <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800 flex gap-2">
                    <i class="fas fa-lightbulb mt-0.5"></i>
                    <span>Setelah tersimpan, sub kegiatan langsung muncul di daftar dan bisa Anda isi laporannya lewat tombol <strong>Tambah</strong>.</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="bg-gray-50 px-4 py-3 border-t flex justify-between gap-2 flex-shrink-0">
            <button type="button" id="wz-btn-prev" onclick="wzPrev()" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100 transition hidden">
                <i class="fas fa-arrow-left mr-1"></i> Kembali
            </button>
            <div class="flex-1"></div>
            <button type="button" onclick="wzClose()" class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-100 transition">Batal</button>
            <button type="button" id="wz-btn-next" onclick="wzNext()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700 transition">
                Lanjut <i class="fas fa-arrow-right ml-1"></i>
            </button>
            <button type="button" id="wz-btn-save" onclick="wzSubmit()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm hover:bg-emerald-700 transition hidden">
                <i class="fas fa-save mr-1"></i> Simpan
            </button>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<style>
.wz-radio-card{ display:flex; align-items:center; gap:.5rem; padding:.7rem .8rem; border:1px solid #e5e7eb; border-radius:.65rem; cursor:pointer; font-size:.78rem; line-height:1.15rem; color:#374151; transition:all .15s; }
.wz-radio-card i{ color:#6366f1; flex-shrink:0; }
.wz-radio-card:hover{ background:#f9fafb; }
.wz-radio-active{ border-color:#6366f1; background:#eef2ff; box-shadow:inset 0 0 0 1px #6366f1; }
</style>
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

/* ═══════════════════════════════════════════════════════════════════
   WIZARD: Tambah Kegiatan Baru (Program → Kegiatan → Sub Kegiatan)
═══════════════════════════════════════════════════════════════════ */
@if($wzCanUse)
var wzIsAdmin = {{ $wzIsAdmin ? 'true' : 'false' }};
var wzOrder   = wzIsAdmin ? ['pd','program','kegiatan','sub','review'] : ['program','kegiatan','sub','review'];
var wzIdx     = 0;
var wzPanel   = { pd:'wz-step-pd', program:'wz-step-1', kegiatan:'wz-step-2', sub:'wz-step-3', review:'wz-step-4' };
var wzLabel   = { pd:'Perangkat Daerah', program:'Program', kegiatan:'Kegiatan', sub:'Sub Kegiatan', review:'Tinjau' };
var wzCsrf    = '{{ csrf_token() }}';
var wzUrls = {
    strategi:        '{{ route("oppkpke.options.strategi") }}',
    programs:        '{{ route("oppkpke.options.programs") }}',
    kegiatan:        '{{ route("oppkpke.options.kegiatan") }}',
    perangkatDaerah: '{{ route("oppkpke.options.perangkat-daerah") }}',
    store:           '{{ route("oppkpke.hierarki.store") }}'
};
var wzStrategiLoaded = false, wzPdLoaded = false;

function wzEsc(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
function wzVal(id){ var el=document.getElementById(id); return el ? (el.value||'').trim() : ''; }
function wzSelText(id){ var el=document.getElementById(id); if(!el)return''; var o=el.options[el.selectedIndex]; return o?o.text:''; }
function wzPdMode(){ var el=document.querySelector('input[name=wz_pd_mode]:checked'); return el?el.value:'existing'; }
function wzProgramMode(){ return document.querySelector('input[name=wz_program_mode]:checked').value; }
function wzKegiatanMode(){ return document.querySelector('input[name=wz_kegiatan_mode]:checked').value; }

function wzBuildStepper(){
    var html = '';
    wzOrder.forEach(function(key,i){
        var last = i===wzOrder.length-1;
        html += '<div class="flex items-center '+(last?'':'flex-1')+'">'
            + '<div class="flex flex-col items-center">'
            + '<div id="wz-sdot-'+i+'" class="wz-dot w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold bg-gray-200 text-gray-500 transition">'+(i+1)+'</div>'
            + '<span class="text-[10px] md:text-xs mt-1 text-gray-500 whitespace-nowrap">'+wzLabel[key]+'</span>'
            + '</div>'
            + (last?'':'<div id="wz-sline-'+i+'" class="flex-1 h-0.5 bg-gray-200 mx-1 md:mx-2 -mt-4 transition"></div>')
            + '</div>';
    });
    document.getElementById('wz-stepper').innerHTML = html;
}

function wzOpen(){
    wzIdx = 0;
    document.querySelectorAll('#wz-modal input[type=text]').forEach(function(i){ i.value=''; });
    document.querySelector('input[name=wz_program_mode][value=existing]').checked = true;
    document.querySelector('input[name=wz_kegiatan_mode][value=existing]').checked = true;
    if (wzIsAdmin){
        document.querySelector('input[name=wz_pd_mode][value=existing]').checked = true;
        wzSetPdMode('existing');
        wzLoadPds();
    }
    wzForceProgramNew(false);
    wzForceKegiatanNew(false);
    document.getElementById('wz-kegiatan-id').innerHTML = '<option value="">-- Pilih program dulu --</option>';
    wzSetProgramMode('existing');
    wzSetKegiatanMode('existing');
    wzLoadStrategi();
    if (!wzIsAdmin) wzLoadPrograms();   // operator: program otomatis PD sendiri
    wzBuildStepper();
    wzShow(0);
    var m = document.getElementById('wz-modal');
    m.classList.remove('hidden'); m.classList.add('flex');
}
function wzClose(){ var m=document.getElementById('wz-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
function wzToggleGuide(){
    var b=document.getElementById('wz-guide-body'), c=document.getElementById('wz-guide-chevron');
    var hidden=b.classList.toggle('hidden');
    c.style.transform = hidden ? 'rotate(0deg)' : 'rotate(180deg)';
}

function wzShow(idx){
    wzIdx = idx;
    document.querySelectorAll('#wz-modal .wz-step').forEach(function(p){ p.classList.add('hidden'); });
    var key = wzOrder[idx];
    document.getElementById(wzPanel[key]).classList.remove('hidden');
    for (var i=0;i<wzOrder.length;i++){
        var dot = document.getElementById('wz-sdot-'+i);
        dot.className = 'wz-dot w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition ' +
            (i<idx ? 'bg-emerald-500 text-white' : (i===idx ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'));
        dot.innerHTML = i<idx ? '<i class="fas fa-check"></i>' : (i+1);
        var line = document.getElementById('wz-sline-'+i);
        if (line) line.className = 'flex-1 h-0.5 mx-1 md:mx-2 -mt-4 transition ' + (i<idx ? 'bg-emerald-500' : 'bg-gray-200');
    }
    var isReview = key==='review';
    document.getElementById('wz-btn-prev').classList.toggle('hidden', idx===0);
    document.getElementById('wz-btn-next').classList.toggle('hidden', isReview);
    document.getElementById('wz-btn-save').classList.toggle('hidden', !isReview);
    if (isReview) wzRenderReview();
}

function wzSyncCards(group){
    document.querySelectorAll('.wz-radio-card[data-for="'+group+'"]').forEach(function(card){
        card.classList.toggle('wz-radio-active', card.querySelector('input').checked);
    });
}
function wzSetPdMode(mode){
    document.getElementById('wz-pd-existing').classList.toggle('hidden', mode!=='existing');
    document.getElementById('wz-pd-new').classList.toggle('hidden', mode!=='new');
    wzSyncCards('pd');
}
function wzSetProgramMode(mode){
    document.getElementById('wz-prog-existing').classList.toggle('hidden', mode!=='existing');
    document.getElementById('wz-prog-new').classList.toggle('hidden', mode!=='new');
    wzSyncCards('program');
    if (mode==='new') wzLoadStrategi();
}
function wzSetKegiatanMode(mode){
    document.getElementById('wz-keg-existing').classList.toggle('hidden', mode!=='existing');
    document.getElementById('wz-keg-new').classList.toggle('hidden', mode!=='new');
    wzSyncCards('kegiatan');
}
function wzForceProgramNew(force){
    if (force){
        document.querySelector('input[name=wz_program_mode][value=new]').checked = true;
        wzSetProgramMode('new');
        document.getElementById('wz-prog-mode-wrap').classList.add('hidden');
        document.getElementById('wz-prog-forcenew').classList.remove('hidden');
    } else {
        document.getElementById('wz-prog-mode-wrap').classList.remove('hidden');
        document.getElementById('wz-prog-forcenew').classList.add('hidden');
    }
}
function wzForceKegiatanNew(force){
    if (force){
        document.querySelector('input[name=wz_kegiatan_mode][value=new]').checked = true;
        wzSetKegiatanMode('new');
        document.getElementById('wz-keg-mode-wrap').classList.add('hidden');
        document.getElementById('wz-keg-forcenew').classList.remove('hidden');
    } else {
        document.getElementById('wz-keg-mode-wrap').classList.remove('hidden');
        document.getElementById('wz-keg-forcenew').classList.add('hidden');
    }
}

function wzLoadStrategi(){
    if (wzStrategiLoaded) return;
    $.get(wzUrls.strategi).done(function(list){
        document.getElementById('wz-strategi-id').innerHTML = '<option value="">-- Pilih Strategi --</option>' +
            list.map(function(s){ return '<option value="'+s.id+'">'+wzEsc((s.kode?s.kode+' — ':'')+s.nama)+'</option>'; }).join('');
        wzStrategiLoaded = true;
    });
}
function wzLoadPds(){
    if (wzPdLoaded) return;
    $.get(wzUrls.perangkatDaerah).done(function(list){
        document.getElementById('wz-pd-id').innerHTML = '<option value="">-- Pilih Perangkat Daerah --</option>' +
            list.map(function(p){ return '<option value="'+p.id+'">'+wzEsc(p.nama)+'</option>'; }).join('');
        wzPdLoaded = true;
    });
}
function wzLoadPrograms(pdId){
    var data = pdId ? { perangkat_daerah_id: pdId } : {};
    $.get(wzUrls.programs, data).done(function(list){
        var sel = document.getElementById('wz-program-id');
        if (!list.length){ sel.innerHTML = '<option value="">(Belum ada program — silakan buat baru)</option>'; return; }
        sel.innerHTML = '<option value="">-- Pilih Program --</option>' +
            list.map(function(p){ return '<option value="'+p.id+'">'+wzEsc((p.kode?p.kode+' — ':'')+p.nama_program)+'</option>'; }).join('');
    });
}
function wzLoadKegiatan(programId){
    var sel = document.getElementById('wz-kegiatan-id');
    sel.innerHTML = '<option value="">-- Memuat... --</option>';
    $.get(wzUrls.kegiatan, { program_id: programId }).done(function(list){
        if (!list.length){ sel.innerHTML = '<option value="">(Belum ada kegiatan — silakan buat baru)</option>'; return; }
        sel.innerHTML = '<option value="">-- Pilih Kegiatan --</option>' +
            list.map(function(k){ return '<option value="'+k.id+'">'+wzEsc((k.kode?k.kode+' — ':'')+k.nama_kegiatan)+'</option>'; }).join('');
    });
}

function wzValidateKey(key){
    if (key==='pd'){
        if (wzPdMode()==='existing'){ if (!wzVal('wz-pd-id')){ showToast('Pilih perangkat daerah tujuan','error'); return false; } }
        else { if (!wzVal('wz-pd-nama')){ showToast('Isi nama perangkat daerah','error'); return false; } }
    } else if (key==='program'){
        if (wzProgramMode()==='existing'){ if (!wzVal('wz-program-id')){ showToast('Pilih program terlebih dahulu','error'); return false; } }
        else {
            if (!wzVal('wz-strategi-id')){ showToast('Pilih strategi','error'); return false; }
            if (!wzVal('wz-kode-program')){ showToast('Isi kode program','error'); return false; }
            if (!wzVal('wz-nama-program')){ showToast('Isi nama program','error'); return false; }
        }
    } else if (key==='kegiatan'){
        if (wzKegiatanMode()==='existing'){ if (!wzVal('wz-kegiatan-id')){ showToast('Pilih kegiatan terlebih dahulu','error'); return false; } }
        else { if (!wzVal('wz-nama-kegiatan')){ showToast('Isi nama kegiatan','error'); return false; } }
    } else if (key==='sub'){
        if (!wzVal('wz-nama-sub')){ showToast('Isi nama sub kegiatan','error'); return false; }
    }
    return true;
}

function wzNext(){
    var key = wzOrder[wzIdx];
    if (!wzValidateKey(key)) return;
    if (key==='pd'){
        if (wzPdMode()==='new'){
            // PD baru → tidak ada program lama → program (dan kegiatan) wajib baru.
            wzForceProgramNew(true);
        } else {
            wzForceProgramNew(false);
            wzLoadPrograms(wzVal('wz-pd-id'));
        }
    }
    if (key==='program'){
        if (wzProgramMode()==='new'){ wzForceKegiatanNew(true); }
        else { wzForceKegiatanNew(false); wzLoadKegiatan(wzVal('wz-program-id')); }
    }
    if (wzIdx < wzOrder.length-1) wzShow(wzIdx+1);
}
function wzPrev(){ if (wzIdx>0) wzShow(wzIdx-1); }

function wzRenderReview(){
    function row(label,val,isNew){
        return '<div><div class="text-[11px] uppercase tracking-wide text-gray-400">'+label+
            (isNew?' <span class="text-emerald-600 normal-case tracking-normal font-semibold">(baru)</span>':'')+'</div>'+
            '<div class="font-medium text-gray-800 break-words">'+wzEsc(val||'-')+'</div></div>';
    }
    var html = '';
    if (wzIsAdmin){
        var pdTxt = wzPdMode()==='existing' ? wzSelText('wz-pd-id')
            : wzVal('wz-pd-nama') + (wzVal('wz-pd-singkatan') ? ' ('+wzVal('wz-pd-singkatan')+')' : '');
        html += row('Perangkat Daerah', pdTxt, wzPdMode()==='new');
    }
    var progTxt = wzProgramMode()==='existing' ? wzSelText('wz-program-id')
        : (wzVal('wz-kode-program')?wzVal('wz-kode-program')+' — ':'')+wzVal('wz-nama-program');
    var kegTxt  = wzKegiatanMode()==='existing' ? wzSelText('wz-kegiatan-id')
        : (wzVal('wz-kode-kegiatan')?wzVal('wz-kode-kegiatan')+' — ':'')+wzVal('wz-nama-kegiatan');
    var subTxt  = (wzVal('wz-kode-sub')?wzVal('wz-kode-sub')+' — ':'')+wzVal('wz-nama-sub');
    html += row('Program', progTxt, wzProgramMode()==='new')
          + row('Kegiatan', kegTxt, wzKegiatanMode()==='new')
          + row('Sub Kegiatan', subTxt, true);
    document.getElementById('wz-review').innerHTML = html;
}

function wzSubmit(){
    var btn = document.getElementById('wz-btn-save');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';
    var payload = {
        _token: wzCsrf,
        program_mode: wzProgramMode(),
        kegiatan_mode: wzProgramMode()==='new' ? 'new' : wzKegiatanMode(),
        nama_sub_kegiatan: wzVal('wz-nama-sub'),
        kode_sub: wzVal('wz-kode-sub')
    };
    if (wzIsAdmin){
        payload.pd_mode = wzPdMode();
        if (wzPdMode()==='existing'){ payload.perangkat_daerah_id = wzVal('wz-pd-id'); }
        else { payload.pd_nama = wzVal('wz-pd-nama'); payload.pd_singkatan = wzVal('wz-pd-singkatan'); }
    }
    if (payload.program_mode==='existing'){ payload.program_id = wzVal('wz-program-id'); }
    else { payload.strategi_id = wzVal('wz-strategi-id'); payload.kode_program = wzVal('wz-kode-program'); payload.nama_program = wzVal('wz-nama-program'); }
    if (payload.kegiatan_mode==='existing'){ payload.kegiatan_id = wzVal('wz-kegiatan-id'); }
    else { payload.nama_kegiatan = wzVal('wz-nama-kegiatan'); payload.kode_kegiatan = wzVal('wz-kode-kegiatan'); }

    $.ajax({ url: wzUrls.store, method:'POST', data: payload })
        .done(function(res){ showToast(res.message || 'Berhasil ditambahkan', 'success'); wzClose(); loadData(); })
        .fail(function(xhr){
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message
                : (xhr.responseJSON && xhr.responseJSON.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : 'Gagal menyimpan.');
            showToast(msg, 'error');
        })
        .always(function(){ btn.disabled=false; btn.innerHTML='<i class="fas fa-save mr-1"></i> Simpan'; });
}

// Program existing berubah → reset pilihan kegiatan ke mode existing.
document.addEventListener('change', function(e){
    if (e.target && e.target.id==='wz-program-id' && wzProgramMode()==='existing'){
        document.querySelector('input[name=wz_kegiatan_mode][value=existing]').checked = true;
        wzSetKegiatanMode('existing');
    }
    // Admin ganti PD existing → muat ulang program PD itu & reset ke mode existing.
    if (e.target && e.target.id==='wz-pd-id' && wzPdMode()==='existing'){
        document.querySelector('input[name=wz_program_mode][value=existing]').checked = true;
        wzForceProgramNew(false);
        wzSetProgramMode('existing');
    }
});
@endif
</script>
@endpush
