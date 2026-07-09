@extends('layouts.oppkpke')

@section('title', 'Identitas PIC')
@section('page-title', 'Identitas PIC')
@section('page-subtitle', 'Penanggung jawab data — wajib diisi sebelum menginput laporan')

@section('content')
@php $user = auth()->user(); @endphp
<div class="max-w-2xl mx-auto space-y-5">

    <div class="bg-blue-50 border border-blue-200 text-blue-800 text-sm rounded-lg px-4 py-3 flex items-start gap-2">
        <i class="fas fa-circle-info mt-0.5"></i>
        <p>Setiap laporan yang Anda input tercatat atas nama <strong>PIC (Penanggung Jawab)</strong> dan diaudit oleh Tim IT.
        Isi data yang <strong>benar sesuai KTP</strong>.
        <button type="button" onclick="openModal('modalAturan')" class="font-semibold underline hover:text-blue-900">Baca Aturan &amp; Sanksi</button>.</p>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         IDENTITAS UTAMA (akun operator ini)
    ══════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border shadow-sm p-6">
        <h3 class="font-semibold text-gray-800 mb-1 flex items-center gap-2">
            <i class="fas fa-id-card text-blue-600"></i> Identitas Anda (PIC Utama)
        </h3>
        <p class="text-xs text-gray-500 mb-4">Identitas akun Anda sebagai penginput utama.</p>

        <form method="POST" action="{{ route('oppkpke.pic.save') }}" class="space-y-4" id="picForm">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $user->nama_lengkap) }}"
                       required minlength="3" maxlength="120" autocomplete="name"
                       placeholder="Nama lengkap sesuai KTP"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 @error('nama_lengkap') border-red-400 @enderror">
                @error('nama_lengkap')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor KTP / NIK <span class="text-red-500">*</span></label>
                <input type="text" name="no_ktp" value="{{ old('no_ktp', $user->no_ktp) }}"
                       required inputmode="numeric" pattern="\d{16}" maxlength="16" autocomplete="off"
                       oninput="this.value=this.value.replace(/\D/g,'').slice(0,16)"
                       placeholder="16 digit angka"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-mono tracking-wide focus:ring-2 focus:ring-blue-500 @error('no_ktp') border-red-400 @enderror">
                @error('no_ktp')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-gray-400 mt-1">NIK 16 digit, divalidasi formatnya. Disimpan aman &amp; ditampilkan tersamar pada audit.</p>
            </div>

            <label class="flex items-start gap-2 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" required class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>Saya menyatakan data yang saya isi <strong>benar sesuai KTP</strong> dan bersedia menerima sanksi bila terbukti memalsukan data.</span>
            </label>

            <button type="submit" id="picSubmit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                <i class="fas fa-floppy-disk"></i> Simpan Identitas &amp; Lanjut Input Laporan
            </button>
        </form>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         PIC TAMBAHAN (undang PIC lain — hanya catatan, tanpa login)
    ══════════════════════════════════════════════════════════ --}}
    @if($user->isDaerah() && $user->perangkat_daerah_id)
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <div class="px-5 md:px-6 py-4 border-b bg-gray-50 flex items-center justify-between gap-2">
            <div>
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="fas fa-user-group text-purple-600"></i> PIC Tambahan
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Daftar penanggung jawab lain di perangkat daerah Anda (tanpa akun login).</p>
            </div>
            <button type="button" onclick="openModal('modalTambahPic')"
                    class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold px-3 py-2 rounded-lg transition flex items-center gap-1.5 flex-shrink-0">
                <i class="fas fa-user-plus"></i> Tambah PIC
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wide">
                    <tr>
                        <th class="px-5 py-3 text-left">Nama Lengkap</th>
                        <th class="px-5 py-3 text-left">NIK</th>
                        <th class="px-5 py-3 text-center w-20">Aksi</th>
                    </tr>
                </thead>
                <tbody id="picTableBody" class="divide-y divide-gray-100">
                    @forelse($pics as $pic)
                    <tr id="pic-row-{{ $pic->id }}" class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $pic->nama_lengkap }}</td>
                        <td class="px-5 py-3 font-mono text-gray-500 text-xs">{{ $pic->ktp_masked }}</td>
                        <td class="px-5 py-3 text-center">
                            <button type="button" onclick="hapusPic({{ $pic->id }}, @js($pic->nama_lengkap))"
                                    class="text-red-500 hover:text-red-700 transition" title="Hapus PIC">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr id="picEmptyRow">
                        <td colspan="3" class="px-5 py-10 text-center text-gray-400">
                            <i class="fas fa-user-group text-3xl mb-2 block"></i>
                            <p class="text-sm">Belum ada PIC tambahan.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL: TAMBAH PIC
══════════════════════════════════════════════════════════ --}}
<div id="modalTambahPic" class="fixed inset-0 z-[150] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalTambahPic')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-5 py-4 flex items-center justify-between">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm">
                <i class="fas fa-user-plus"></i> Tambah PIC
            </h3>
            <button onclick="closeModal('modalTambahPic')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div id="picFormError" class="hidden bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm"></div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" id="picNama" maxlength="120" placeholder="Nama lengkap sesuai KTP"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor KTP / NIK <span class="text-red-500">*</span></label>
                <input type="text" id="picKtp" inputmode="numeric" maxlength="16" autocomplete="off"
                       oninput="this.value=this.value.replace(/\D/g,'').slice(0,16)"
                       placeholder="16 digit angka"
                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm font-mono tracking-wide focus:ring-2 focus:ring-purple-500">
                <p class="text-xs text-gray-400 mt-1">Format NIK divalidasi. Satu NIK hanya boleh didaftarkan sekali.</p>
            </div>

            <label class="flex items-start gap-2 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" id="picAgree" class="mt-0.5 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                <span>Data PIC ini <strong>benar sesuai KTP</strong>. Saya paham <button type="button" onclick="openModal('modalAturan')" class="underline text-purple-600">aturan &amp; sanksi</button> pengisian data palsu.</span>
            </label>

            <div class="flex gap-2 pt-1">
                <button onclick="closeModal('modalTambahPic')" class="flex-1 border border-gray-300 text-gray-700 py-2 rounded-lg text-sm hover:bg-gray-50 transition">Batal</button>
                <button id="picAddBtn" onclick="simpanPic()" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-save mr-1"></i> Simpan PIC
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     MODAL: ATURAN & SANKSI
══════════════════════════════════════════════════════════ --}}
<div id="modalAturan" class="fixed inset-0 z-[160] hidden items-center justify-center p-3 md:p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal('modalAturan')"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 flex flex-col max-h-[88vh] overflow-hidden">
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-5 py-4 flex items-center justify-between flex-shrink-0">
            <h3 class="text-white font-semibold flex items-center gap-2 text-sm md:text-base">
                <i class="fas fa-scale-balanced"></i> Aturan Pengisian Identitas &amp; Sanksi
            </h3>
            <button onclick="closeModal('modalAturan')" class="text-white/70 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <div class="p-5 md:p-6 overflow-y-auto space-y-5 text-sm text-gray-700">
            <div>
                <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-list-check text-blue-500"></i> Aturan Pengisian</h4>
                <ol class="list-decimal ml-5 space-y-1.5 text-gray-600">
                    <li>Nama lengkap &amp; NIK wajib <strong>sesuai KTP yang sah dan berlaku</strong>.</li>
                    <li>Dilarang mengisi NIK atau nama secara <strong>palsu, acak, atau mengada-ada</strong>.</li>
                    <li>Satu NIK hanya boleh didaftarkan <strong>satu kali</strong> di sistem.</li>
                    <li>Setiap penambahan PIC &amp; input laporan <strong>tercatat &amp; diaudit</strong> oleh Tim IT (nama operator, waktu, alamat IP).</li>
                </ol>
            </div>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2 flex items-center gap-2"><i class="fas fa-triangle-exclamation text-red-500"></i> Sanksi Pelanggaran</h4>
                <p class="text-gray-600 mb-2">Bila terbukti mengisi data palsu/ngawur atau menyalahgunakan data:</p>
                <ol class="list-decimal ml-5 space-y-1.5 text-gray-600">
                    <li><strong>Peringatan tertulis</strong> dan <strong>penonaktifan sementara</strong> akun operator.</li>
                    <li><strong>Penonaktifan permanen</strong> akun &amp; pencabutan hak akses, dilaporkan ke Admin dan pimpinan Perangkat Daerah.</li>
                    <li><strong>Pertanggungjawaban hukum</strong> atas pemalsuan/penyalahgunaan data pribadi sesuai ketentuan yang berlaku — antara lain UU No. 24 Tahun 2013 tentang Administrasi Kependudukan, UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi, dan UU ITE.</li>
                </ol>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800">
                <i class="fas fa-circle-info mr-1"></i>
                Sistem memvalidasi <strong>format</strong> NIK (16 digit, kode wilayah, tanggal lahir). Validasi format bukan jaminan keaslian — kebenaran data tetap menjadi tanggung jawab penuh Anda.
            </div>
        </div>
        <div class="px-5 py-3 border-t bg-gray-50 flex-shrink-0 text-right">
            <button onclick="closeModal('modalAturan')" class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-semibold px-5 py-2 rounded-lg transition">Saya Mengerti</button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function openModal(id)  { var el = document.getElementById(id); el.classList.remove('hidden'); el.classList.add('flex'); }
function closeModal(id) { var el = document.getElementById(id); el.classList.add('hidden');  el.classList.remove('flex'); }

// Anti double-submit form identitas utama
document.getElementById('picForm').addEventListener('submit', function () {
    var b = document.getElementById('picSubmit');
    b.disabled = true; b.classList.add('opacity-70');
});

function picErr(msg) {
    var el = document.getElementById('picFormError');
    el.textContent = msg; el.classList.remove('hidden');
}

// ── Tambah PIC (AJAX) ──────────────────────────────────────────────
function simpanPic() {
    document.getElementById('picFormError').classList.add('hidden');
    var nama  = document.getElementById('picNama').value.trim();
    var ktp   = document.getElementById('picKtp').value.trim();
    var agree = document.getElementById('picAgree').checked;

    if (nama.length < 3)      return picErr('Nama lengkap minimal 3 karakter.');
    if (!/^\d{16}$/.test(ktp)) return picErr('Nomor KTP (NIK) harus tepat 16 digit angka.');
    if (!agree)               return picErr('Centang pernyataan kebenaran data terlebih dahulu.');

    var btn = document.getElementById('picAddBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

    $.ajax({
        url: '{{ route('oppkpke.pic.invite') }}', method: 'POST',
        data: { nama_lengkap: nama, no_ktp: ktp },
    }).done(function (res) {
        // Bersihkan empty-row bila ada
        var empty = document.getElementById('picEmptyRow');
        if (empty) empty.remove();

        var tr = document.createElement('tr');
        tr.id = 'pic-row-' + res.pic.id;
        tr.className = 'hover:bg-gray-50 transition';
        tr.innerHTML =
            '<td class="px-5 py-3 font-medium text-gray-800"></td>' +
            '<td class="px-5 py-3 font-mono text-gray-500 text-xs">' + res.pic.ktp_masked + '</td>' +
            '<td class="px-5 py-3 text-center"><button type="button" class="text-red-500 hover:text-red-700" title="Hapus PIC"><i class="fas fa-trash-alt"></i></button></td>';
        tr.querySelector('td').textContent = res.pic.nama_lengkap;                 // aman dari XSS
        tr.querySelector('button').onclick = function () { hapusPic(res.pic.id, res.pic.nama_lengkap); };
        document.getElementById('picTableBody').appendChild(tr);

        document.getElementById('picNama').value = '';
        document.getElementById('picKtp').value  = '';
        document.getElementById('picAgree').checked = false;
        closeModal('modalTambahPic');
        showToast(res.message, 'success');
    }).fail(function (xhr) {
        var msg = 'Gagal menambahkan PIC.';
        if (xhr.responseJSON) {
            msg = xhr.responseJSON.message
               || (xhr.responseJSON.errors ? Object.values(xhr.responseJSON.errors).flat().join(' ') : msg);
        }
        picErr(msg);
    }).always(function () {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save mr-1"></i> Simpan PIC';
    });
}

// ── Hapus PIC ──────────────────────────────────────────────────────
function hapusPic(id, nama) {
    if (!confirm('Hapus PIC "' + nama + '"?')) return;
    $.ajax({
        url: '{{ url('oppkpke/pic') }}/' + id, method: 'POST',
        data: { _method: 'DELETE' },
    }).done(function (res) {
        var row = document.getElementById('pic-row-' + id);
        if (row) row.remove();
        showToast(res.message, 'success');
    }).fail(function (xhr) {
        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Gagal menghapus PIC.';
        showToast(msg, 'error');
    });
}
</script>
@endpush
