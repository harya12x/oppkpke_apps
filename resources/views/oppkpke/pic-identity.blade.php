@extends('layouts.oppkpke')

@section('title', 'Lengkapi Identitas PIC')
@section('page-title', 'Lengkapi Identitas PIC')
@section('page-subtitle', 'Wajib diisi sebelum menginput laporan')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3 mb-4 flex items-start gap-2">
        <i class="fas fa-circle-info mt-0.5"></i>
        <p>Setiap laporan yang Anda input akan tercatat atas nama <strong>Anda sebagai PIC</strong> (Penanggung Jawab).
        Data ini dipakai untuk penelusuran audit oleh Tim IT. Isi dengan data yang benar.</p>
    </div>

    <div class="bg-white rounded-xl border shadow-sm p-6">
        <form method="POST" action="{{ route('oppkpke.pic.save') }}" class="space-y-4" id="picForm">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', auth()->user()->nama_lengkap) }}"
                       required minlength="3" maxlength="120" autocomplete="name"
                       placeholder="Nama lengkap sesuai KTP"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 @error('nama_lengkap') border-red-400 @enderror">
                @error('nama_lengkap')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor KTP / NIK <span class="text-red-500">*</span></label>
                <input type="text" name="no_ktp" value="{{ old('no_ktp', auth()->user()->no_ktp) }}"
                       required inputmode="numeric" pattern="\d{16}" maxlength="16"
                       autocomplete="off"
                       oninput="this.value=this.value.replace(/\D/g,'').slice(0,16)"
                       placeholder="16 digit angka"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-mono tracking-wide focus:ring-2 focus:ring-blue-500 @error('no_ktp') border-red-400 @enderror">
                @error('no_ktp')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">NIK 16 digit. Disimpan aman & hanya ditampilkan tersamar pada audit.</p>
            </div>
            <button type="submit" id="picSubmit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                <i class="fas fa-id-card"></i> Simpan & Lanjut Input Laporan
            </button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Anti double-submit
    document.getElementById('picForm').addEventListener('submit', function () {
        var b = document.getElementById('picSubmit');
        b.disabled = true; b.classList.add('opacity-70');
    });
</script>
@endpush
