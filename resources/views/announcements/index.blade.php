@extends('layouts.oppkpke')

@section('title', 'Pengumuman')
@section('page-title', 'Pengumuman & Maintenance')
@section('page-subtitle', 'Informasi yang tampil untuk semua pengguna kecuali Tim IT')

@section('content')
@php
    $types = ['info' => 'Informasi', 'warning' => 'Peringatan', 'maintenance' => 'Pemeliharaan', 'critical' => 'Kritis'];
    $typeColor = [
        'info' => 'bg-blue-100 text-blue-700', 'warning' => 'bg-yellow-100 text-yellow-700',
        'maintenance' => 'bg-orange-100 text-orange-700', 'critical' => 'bg-red-100 text-red-700',
    ];
@endphp
<div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-5 gap-6">

    {{-- Form buat pengumuman --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border shadow-sm p-5 sticky top-20">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2 mb-4">
                <i class="fas fa-bullhorn text-amber-500"></i> Buat Pengumuman
            </h3>
            <form method="POST" action="{{ route('oppkpke.announcements.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-xs font-medium text-gray-600 block mb-1">Judul <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="160" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    @error('title')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 block mb-1">Isi <span class="text-red-500">*</span></label>
                    <textarea name="body" rows="4" maxlength="5000" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">{{ old('body') }}</textarea>
                    @error('body')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 block mb-1">Jenis</label>
                    <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500">
                        @foreach($types as $val => $lbl)
                            <option value="{{ $val }}" {{ old('type', 'maintenance') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Mulai (opsional)</label>
                        <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                               class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 block mb-1">Berakhir (opsional)</label>
                        <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                               class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                @error('ends_at')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    Aktifkan langsung
                </label>
                <button type="submit" class="w-full bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium py-2.5 rounded-lg transition">
                    <i class="fas fa-paper-plane mr-1"></i> Terbitkan
                </button>
            </form>
        </div>
    </div>

    {{-- Daftar pengumuman --}}
    <div class="lg:col-span-3 space-y-3">
        @forelse($announcements as $ann)
            @php
                $now = now();
                $expired   = $ann->is_active && $ann->ends_at && $ann->ends_at < $now;
                $scheduled = $ann->is_active && $ann->starts_at && $ann->starts_at > $now;
                $live      = $ann->is_active && !$expired && !$scheduled;
            @endphp
            <div class="bg-white rounded-xl border shadow-sm p-4 {{ $ann->is_active ? '' : 'opacity-60' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full {{ $typeColor[$ann->type] ?? 'bg-gray-100 text-gray-600' }}">{{ $ann->type_label }}</span>
                            @if($live)
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-green-600"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Tayang</span>
                            @elseif($expired)
                                <span class="text-[10px] font-semibold text-red-400">Kedaluwarsa</span>
                            @elseif($scheduled)
                                <span class="text-[10px] font-semibold text-amber-500">Terjadwal</span>
                            @else
                                <span class="text-[10px] font-semibold text-gray-400">Nonaktif</span>
                            @endif
                        </div>
                        <p class="font-semibold text-gray-800 mt-1">{{ $ann->title }}</p>
                        <p class="text-sm text-gray-600 mt-0.5 whitespace-pre-line line-clamp-3">{{ $ann->body }}</p>
                        <p class="text-xs text-gray-400 mt-2">
                            <i class="fas fa-user mr-1"></i>{{ $ann->creator->name ?? 'Sistem' }}
                            · {{ $ann->created_at->diffForHumans() }}
                            @if($ann->starts_at || $ann->ends_at)
                                · <i class="fas fa-clock mr-1"></i>{{ optional($ann->starts_at)->format('d/m/Y H:i') ?? '…' }} — {{ optional($ann->ends_at)->format('d/m/Y H:i') ?? '…' }}
                            @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button type="button" title="Edit"
                                data-id="{{ $ann->id }}"
                                data-title="{{ $ann->title }}"
                                data-body="{{ $ann->body }}"
                                data-type="{{ $ann->type }}"
                                data-active="{{ $ann->is_active ? 1 : 0 }}"
                                data-starts="{{ optional($ann->starts_at)->format('Y-m-d\TH:i') }}"
                                data-ends="{{ optional($ann->ends_at)->format('Y-m-d\TH:i') }}"
                                onclick="openEditAnnBtn(this)"
                                class="w-8 h-8 rounded-lg text-blue-600 hover:bg-blue-50 transition"><i class="fas fa-pen text-xs"></i></button>
                        <form method="POST" action="{{ route('oppkpke.announcements.toggle', $ann) }}">
                            @csrf @method('PATCH')
                            <button type="submit" title="{{ $ann->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                    class="w-8 h-8 rounded-lg {{ $ann->is_active ? 'text-orange-500 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50' }} transition">
                                <i class="fas {{ $ann->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('oppkpke.announcements.destroy', $ann) }}" onsubmit="return confirm('Hapus pengumuman ini?');">
                            @csrf @method('DELETE')
                            <button type="submit" title="Hapus" class="w-8 h-8 rounded-lg text-red-500 hover:bg-red-50 transition"><i class="fas fa-trash text-xs"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-xl border shadow-sm py-16 text-center text-gray-400">
                <i class="fas fa-bullhorn text-4xl mb-3 block"></i>
                <p class="font-medium">Belum ada pengumuman</p>
            </div>
        @endforelse

        <div>{{ $announcements->links() }}</div>
    </div>
</div>

{{-- Modal edit --}}
<div id="editAnnModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" onclick="closeEditAnn()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-pen text-blue-500 mr-2"></i>Edit Pengumuman</h3>
            <button onclick="closeEditAnn()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
        </div>
        <form id="editAnnForm" method="POST" class="p-5 space-y-3">
            @csrf @method('PUT')
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Judul</label>
                <input type="text" name="title" id="ea_title" maxlength="160" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Isi</label>
                <textarea name="body" id="ea_body" rows="4" maxlength="5000" required
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Jenis</label>
                <select name="type" id="ea_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500">
                    @foreach($types as $val => $lbl)<option value="{{ $val }}">{{ $lbl }}</option>@endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-medium text-gray-600 block mb-1">Mulai</label>
                    <input type="datetime-local" name="starts_at" id="ea_starts" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 block mb-1">Berakhir</label>
                    <input type="datetime-local" name="ends_at" id="ea_ends" class="w-full px-2 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_active" value="1" id="ea_active" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"> Aktif
            </label>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="closeEditAnn()" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    var editForm = document.getElementById('editAnnForm');
    var baseUrl  = "{{ url('oppkpke/pengumuman') }}";
    function openEditAnnBtn(btn) {
        openEditAnn({
            id:        btn.dataset.id,
            title:     btn.dataset.title,
            body:      btn.dataset.body,
            type:      btn.dataset.type,
            is_active: btn.dataset.active === '1',
            starts_at: btn.dataset.starts,
            ends_at:   btn.dataset.ends,
        });
    }
    function openEditAnn(a) {
        editForm.action = baseUrl + '/' + a.id;
        document.getElementById('ea_title').value  = a.title || '';
        document.getElementById('ea_body').value   = a.body || '';
        document.getElementById('ea_type').value   = a.type || 'info';
        document.getElementById('ea_starts').value = a.starts_at || '';
        document.getElementById('ea_ends').value   = a.ends_at || '';
        document.getElementById('ea_active').checked = !!a.is_active;
        var m = document.getElementById('editAnnModal');
        m.classList.remove('hidden'); m.classList.add('flex');
    }
    function closeEditAnn() {
        var m = document.getElementById('editAnnModal');
        m.classList.add('hidden'); m.classList.remove('flex');
    }
</script>
@endpush
