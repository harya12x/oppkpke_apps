@extends('layouts.oppkpke')

@section('title', 'Kelola Strategi')
@section('page-title', 'Kelola Strategi')
@section('page-subtitle', 'Edit label (nama) & deskripsi Strategi OPPKPKE')

@section('content')

<div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
    <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div class="text-xs md:text-sm text-blue-800 space-y-1">
        <p>Ubah <strong>nama</strong> (label) dan deskripsi tiap strategi. Perubahan langsung berlaku di dashboard, matriks, dan pencocokan Import RAT.</p>
        <p class="text-blue-600"><strong>Kode</strong> strategi tidak dapat diubah karena dipakai untuk relasi &amp; pencocokan data.</p>
    </div>
</div>

<div class="space-y-4">
    @foreach($strategis as $s)
    @php
        $color = $s->color ?? 'blue';
        $map = [
            'blue'   => 'bg-blue-100 text-blue-700 border-blue-200',
            'green'  => 'bg-green-100 text-green-700 border-green-200',
            'orange' => 'bg-orange-100 text-orange-700 border-orange-200',
            'purple' => 'bg-purple-100 text-purple-700 border-purple-200',
            'red'    => 'bg-red-100 text-red-700 border-red-200',
        ];
        $cls = $map[$color] ?? 'bg-gray-100 text-gray-700 border-gray-200';
    @endphp
    <form class="strat-form bg-white rounded-xl border shadow-sm overflow-hidden" data-id="{{ $s->id }}">
        <div class="px-5 py-3 border-b bg-gray-50 flex items-center gap-3">
            <span class="w-9 h-9 rounded-lg border {{ $cls }} flex items-center justify-center font-bold text-sm flex-shrink-0">{{ $s->kode }}</span>
            <div class="min-w-0">
                <p class="text-xs text-gray-400">Kode (tetap)</p>
                <p class="font-mono text-sm text-gray-700">{{ $s->kode }}</p>
            </div>
            @unless($s->is_active)
                <span class="ml-auto text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Nonaktif</span>
            @endunless
        </div>
        <div class="p-5 space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nama / Label Strategi <span class="text-red-500">*</span></label>
                <input type="text" name="nama" maxlength="255" value="{{ $s->nama }}"
                       class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Deskripsi <span class="text-gray-400 font-normal">(opsional)</span></label>
                <textarea name="deskripsi" rows="2" maxlength="1000"
                          class="w-full border rounded-lg text-sm p-2.5 focus:ring-2 focus:ring-blue-500">{{ $s->deskripsi }}</textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    <i class="fas fa-save mr-1"></i> Simpan
                </button>
            </div>
        </div>
    </form>
    @endforeach
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('.strat-form').forEach(function(form){
    form.addEventListener('submit', function(e){
        e.preventDefault();
        var id   = form.getAttribute('data-id');
        var nama = form.querySelector('input[name=nama]').value.trim();
        if (!nama){ showToast('Nama strategi wajib diisi', 'error'); return; }
        var btn = form.querySelector('button[type=submit]');
        var old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        $.ajax({
            url: '{{ url('admin/strategi') }}/' + id,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'PATCH',
                nama: nama,
                deskripsi: form.querySelector('textarea[name=deskripsi]').value
            }
        })
        .done(function(res){ showToast(res.message || 'Tersimpan', 'success'); })
        .fail(function(xhr){ showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan', 'error'); })
        .always(function(){ btn.disabled = false; btn.innerHTML = old; });
    });
});
</script>
@endpush
