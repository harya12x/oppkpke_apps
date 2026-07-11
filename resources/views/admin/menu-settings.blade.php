@extends('layouts.oppkpke')

@section('title', 'Kelola Menu')
@section('page-title', 'Kelola Menu')
@section('page-subtitle', 'Aktifkan / nonaktifkan menu untuk Admin Master & Operator Daerah')

@section('content')

<div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5">
    <i class="fas fa-circle-info text-blue-500 mt-0.5 flex-shrink-0"></i>
    <div class="text-xs md:text-sm text-blue-800 space-y-1">
        <p>Nonaktifkan menu untuk menyembunyikannya dari sidebar role terkait. Perubahan berlaku setelah pengguna memuat ulang halaman.</p>
        <p class="text-blue-600">Menu yang dinonaktifkan disembunyikan dari tampilan. Untuk mencegah terkunci, sisakan minimal menu Dashboard/Beranda tetap aktif.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    @foreach($catalog as $role => $items)
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 flex items-center justify-between">
            <span class="font-semibold text-gray-800 text-sm flex items-center gap-2">
                <i class="fas {{ $role === 'master' ? 'fa-user-shield text-purple-500' : 'fa-user-tie text-blue-500' }}"></i>
                {{ $manager->roleLabel($role) }}
            </span>
            <div class="flex items-center gap-2 text-xs">
                <button type="button" onclick="msToggleAll('{{ $role }}', true)" class="text-green-600 hover:underline">Aktif semua</button>
                <span class="text-gray-300">|</span>
                <button type="button" onclick="msToggleAll('{{ $role }}', false)" class="text-red-600 hover:underline">Nonaktif semua</button>
            </div>
        </div>

        <form class="ms-form" data-role="{{ $role }}">
            <div class="divide-y divide-gray-100">
                @foreach($items as $key => $label)
                @php $on = $states[$role][$key] ?? true; @endphp
                <label class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer">
                    <span class="text-sm text-gray-700">{{ $label }}</span>
                    <span class="relative inline-flex items-center">
                        <input type="checkbox" name="enabled[]" value="{{ $key }}" class="sr-only ms-switch" {{ $on ? 'checked' : '' }}>
                        <span class="ms-track w-10 h-5 rounded-full bg-gray-300 transition"></span>
                        <span class="ms-knob absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full shadow transition"></span>
                    </span>
                </label>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t bg-gray-50 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    <i class="fas fa-save mr-1"></i> Simpan {{ $manager->roleLabel($role) }}
                </button>
            </div>
        </form>
    </div>
    @endforeach
</div>

@endsection

@push('scripts')
<style>
.ms-switch:checked ~ .ms-track { background:#16a34a; }
.ms-switch:checked ~ .ms-knob  { transform:translateX(20px); }
.ms-switch:focus-visible ~ .ms-track { box-shadow:0 0 0 2px #93c5fd; }
</style>
<script>
function msToggleAll(role, on){
    document.querySelectorAll('.ms-form[data-role="'+role+'"] .ms-switch').forEach(function(c){ c.checked = on; });
}

document.querySelectorAll('.ms-form').forEach(function(form){
    form.addEventListener('submit', function(e){
        e.preventDefault();
        var role = form.getAttribute('data-role');
        var enabled = Array.prototype.map.call(form.querySelectorAll('.ms-switch:checked'), function(c){ return c.value; });
        var btn = form.querySelector('button[type=submit]');
        var old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        $.ajax({
            url: '{{ route('admin.menu-settings.update') }}',
            method: 'POST',
            data: { _token: '{{ csrf_token() }}', role: role, enabled: enabled }
        })
        .done(function(res){ showToast(res.message || 'Tersimpan', 'success'); })
        .fail(function(xhr){ showToast((xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan', 'error'); })
        .always(function(){ btn.disabled = false; btn.innerHTML = old; });
    });
});
</script>
@endpush
