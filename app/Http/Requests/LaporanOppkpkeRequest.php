<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaporanOppkpkeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'nullable|exists:laporan_oppkpke,id',
            'sub_kegiatan_id' => [
                'required',
                'exists:sub_kegiatan,id',
                // Cerminkan unique key laporan_sk_tahun_deleted_unique (sub_kegiatan_id+tahun,
                // di antara baris yang belum soft-deleted) supaya duplikat tertangkap sebagai
                // pesan validasi yang ramah, bukan error SQL mentah dari DB.
                Rule::unique('laporan_oppkpke', 'sub_kegiatan_id')
                    ->where(fn ($q) => $q->where('tahun', $this->input('tahun')))
                    ->whereNull('deleted_at')
                    ->ignore($this->input('id')),
            ],
            // +5 tahun dari sekarang, bukan angka tahun tetap yang akan basi.
            'tahun' => 'required|integer|min:2020|max:' . (now()->year + 5),
            'semester' => 'nullable|integer|in:1,2',
            'durasi_pemberian' => 'nullable|string|max:100',
            'besaran_manfaat' => 'nullable|string|max:255',
            'jenis_bantuan' => 'nullable|string|max:100',
            // String, bukan integer — kolom DB-nya longtext (mis. "500 KK", bukan cuma angka).
            'jumlah_sasaran' => 'nullable|string|max:100',
            'satuan_sasaran' => 'nullable|string|max:50',
            'aktivitas_langsung' => 'nullable|string',
            'aktivitas_tidak_langsung' => 'nullable|string',
            'aktivitas_penunjang' => 'nullable|string',
            'penerima_langsung' => 'nullable|numeric|min:0',
            'penerima_tidak_langsung' => 'nullable|numeric|min:0',
            'penerima_penunjang' => 'nullable|numeric|min:0',
            'sumber_pembiayaan' => 'nullable|string|max:50',
            'sifat_bantuan' => 'nullable|string|max:100',
            'lokasi' => 'nullable|string|max:255',
            'alokasi_anggaran' => 'required|numeric|min:0',
            'realisasi_sem1' => 'nullable|numeric|min:0',
            'realisasi_sem2' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sub_kegiatan_id.required' => 'Sub Kegiatan harus dipilih',
            'sub_kegiatan_id.exists' => 'Sub Kegiatan tidak valid',
            'sub_kegiatan_id.unique' => 'Data untuk sub kegiatan dan tahun ini sudah ada.',
            'tahun.required' => 'Tahun harus diisi',
            'alokasi_anggaran.required' => 'Alokasi anggaran harus diisi',
        ];
    }
}