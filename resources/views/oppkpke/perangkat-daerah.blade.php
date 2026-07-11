@extends('layouts.oppkpke')

@section('title', 'Kelola Perangkat Daerah')
@section('page-title', 'Kelola Perangkat Daerah')
@section('page-subtitle', 'Deteksi & gabungkan perangkat daerah duplikat')

@section('content')

{{-- ── Info ─────────────────────────────────────────────────────── --}}
<div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
    <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div class="text-xs md:text-sm text-blue-800 space-y-1">
        <p>Import kadang membuat <strong>perangkat daerah duplikat</strong> (nama beda tanda baca/spasi). Gabungkan yang duplikat: semua <strong>program, sub kegiatan, laporan, PIC &amp; tiket chat</strong> dipindah ke PD utama, operator duplikat dinonaktifkan, lalu PD sumber dihapus.</p>
        <p class="text-blue-600">Aman terhadap relasi data (dijalankan dalam satu transaksi) &amp; tidak dapat dibatalkan otomatis — pilih PD utama dengan benar.</p>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     DUPLIKAT TERDETEKSI
══════════════════════════════════════════════════════════════ --}}
@if($duplicateGroups->isNotEmpty())
<div class="mb-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
        <i class="fas fa-triangle-exclamation text-amber-500"></i>
        Terdeteksi Duplikat ({{ $duplicateGroups->count() }} kelompok)
    </h3>

    <div class="space-y-4">
        @foreach($duplicateGroups as $gi => $group)
        @php
            // Sarankan PD utama = yang punya laporan terbanyak; kalau seri, yang punya kode resmi.
            $suggested = $group->sortByDesc(fn($p) => [$p->laporan_count, $p->kode ? 1 : 0])->first();
        @endphp
        <div class="bg-white rounded-xl border border-amber-200 shadow-sm overflow-hidden" id="dupgroup-{{ $gi }}">
            <div class="px-4 md:px-5 py-3 bg-amber-50 border-b border-amber-200 flex items-center justify-between gap-2">
                <span class="text-xs md:text-sm font-medium text-amber-800">
                    <i class="fas fa-layer-group mr-1"></i> {{ $group->count() }} entri mirip
                </span>
                <button type="button" onclick="openMerge({{ $gi }})"
                        class="bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition flex items-center gap-1.5">
                    <i class="fas fa-code-merge"></i> Gabungkan
                </button>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($group as $pd)
                <label class="flex items-start gap-3 px-4 md:px-5 py-3 hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="target-{{ $gi }}" value="{{ $pd->id }}"
                           data-group="{{ $gi }}" class="mt-1 target-radio text-purple-600 focus:ring-purple-500"
                           {{ $pd->id === $suggested->id ? 'checked' : '' }}>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 text-sm">
                            {{ $pd->nama }}
                            @if($pd->id === $suggested->id)
                                <span class="ml-1 text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full align-middle">disarankan jadi utama</span>
                            @endif
                        </p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-1 text-xs text-gray-500">
                            <span>ID #{{ $pd->id }}</span>
                            <span>Kode: <span class="font-mono">{{ $pd->kode ?? '—' }}</span></span>
                            <span><i class="fas fa-diagram-project mr-1 text-gray-400"></i>{{ $pd->program_count }} program</span>
                            <span><i class="fas fa-file-lines mr-1 text-gray-400"></i>{{ $pd->laporan_count }} laporan</span>
                            <span><i class="fas fa-user mr-1 text-gray-400"></i>{{ $pd->operator_count }} operator</span>
                            @unless($pd->is_active)<span class="text-red-500">nonaktif</span>@endunless
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
            <div class="px-4 md:px-5 py-2 bg-gray-50 border-t text-xs text-gray-500">
                Yang <strong>tidak</strong> dipilih sebagai utama akan digabung ke yang dipilih, lalu dihapus.
            </div>
        </div>
        @endforeach
    </div>
</div>
@else
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
    <i class="fas fa-circle-check text-green-500 text-xl"></i>
    <p class="text-sm text-green-800 font-medium">Tidak ada perangkat daerah duplikat terdeteksi.</p>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════════
     SEMUA PERANGKAT DAERAH
══════════════════════════════════════════════════════════════ --}}
<div class="bg-white rounded-xl border shadow-sm overflow-hidden">
    <div class="px-4 md:px-6 py-3 border-b bg-gray-50">
        <span class="text-xs md:text-sm text-gray-600 font-medium">Semua Perangkat Daerah ({{ $all->count() }})</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wide">
                <tr>
                    <th class="px-5 py-3 text-left w-10">ID</th>
                    <th class="px-5 py-3 text-left">Nama</th>
                    <th class="px-5 py-3 text-left">Kode</th>
                    <th class="px-5 py-3 text-center">Program</th>
                    <th class="px-5 py-3 text-center">Laporan</th>
                    <th class="px-5 py-3 text-center">Operator</th>
                    <th class="px-5 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($all as $pd)
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-gray-400 text-xs">#{{ $pd->id }}</td>
                    <td class="px-5 py-3 font-medium text-gray-800">{{ $pd->nama }}</td>
                    <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $pd->kode ?? '—' }}</td>
                    <td class="px-5 py-3 text-center text-gray-600">{{ $pd->program_count }}</td>
                    <td class="px-5 py-3 text-center text-gray-600">{{ $pd->laporan_count }}</td>
                    <td class="px-5 py-3 text-center text-gray-600">{{ $pd->operator_count }}</td>
                    <td class="px-5 py-3 text-center">
                        @if($pd->is_active)
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Aktif</span>
                        @else
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- ── Modal konfirmasi merge ───────────────────────────────────── --}}
<div id="modalMerge" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalMerge')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-5 py-4 flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm"><i class="fas fa-code-merge"></i> Gabungkan Perangkat Daerah</h3>
            <button onclick="closeModal('modalMerge')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-sm text-gray-600">Semua data dari PD berikut akan dipindahkan lalu PD sumber <strong>dihapus permanen</strong>:</p>
            <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-2" id="mergeSummary"></div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-800">
                <i class="fas fa-triangle-exclamation mr-1"></i> Tindakan ini tidak bisa dibatalkan otomatis. Pastikan PD utama sudah benar.
            </div>
            <div class="flex gap-2 pt-1">
                <button onclick="closeModal('modalMerge')" class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
                <button id="mergeConfirmBtn" onclick="confirmMerge()" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg text-sm font-semibold transition">
                    Ya, Gabungkan
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
var _mergePayload = null;

function openModal(id)  { var el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { var el = document.getElementById(id); el.classList.add('hidden');  el.classList.remove('flex'); }

function openMerge(groupIdx) {
    var target = document.querySelector('input.target-radio[data-group="' + groupIdx + '"]:checked');
    if (!target) { showToast('Pilih PD utama dulu', 'error'); return; }
    var targetId = target.value;

    // Semua radio di grup selain target = sumber.
    var radios = document.querySelectorAll('input.target-radio[data-group="' + groupIdx + '"]');
    var sourceIds = [], targetName = '', sourceNames = [];
    radios.forEach(function (r) {
        var nama = r.closest('label').querySelector('p').textContent.trim();
        if (r.value === targetId) { targetName = nama; }
        else { sourceIds.push(r.value); sourceNames.push(nama); }
    });
    if (sourceIds.length === 0) { showToast('Tidak ada PD sumber untuk digabung', 'error'); return; }

    _mergePayload = { target_id: targetId, source_ids: sourceIds };

    var html = '<p class="text-xs text-gray-500 mb-1">PD Utama (tujuan):</p>'
             + '<p class="font-semibold text-purple-700 mb-2">' + escapeHtml(targetName) + '</p>'
             + '<p class="text-xs text-gray-500 mb-1">Digabung &amp; dihapus:</p>'
             + '<ul class="list-disc ml-4 text-gray-700">'
             + sourceNames.map(function (n) { return '<li>' + escapeHtml(n) + '</li>'; }).join('')
             + '</ul>';
    document.getElementById('mergeSummary').innerHTML = html;
    openModal('modalMerge');
}

function confirmMerge() {
    if (!_mergePayload) return;
    var btn = document.getElementById('mergeConfirmBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

    $.ajax({
        url: '{{ route('admin.perangkat-daerah.merge') }}',
        method: 'POST',
        data: _mergePayload,
    }).done(function (res) {
        showToast(res.message, 'success');
        setTimeout(function () { location.reload(); }, 1200);
    }).fail(function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message
                : (xhr.responseJSON && xhr.responseJSON.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : 'Gagal menggabungkan.');
        showToast(msg, 'error');
        btn.disabled = false; btn.innerHTML = 'Ya, Gabungkan';
        closeModal('modalMerge');
    });
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
</script>
@endpush
