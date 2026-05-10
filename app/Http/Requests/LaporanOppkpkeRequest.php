<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaporanOppkpkeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sub_kegiatan_id' => 'required|exists:sub_kegiatan,id',
            'tahun' => 'required|integer|min:2020|max:2050',
            'semester' => 'nullable|integer|in:1,2',
            'durasi_pemberian' => 'nullable|string|max:255',
            'besaran_manfaat' => 'nullable|string|max:255',
            'jenis_bantuan' => 'nullable|string|max:255',
            'jumlah_sasaran' => 'nullable|integer|min:0',
            'penerima_langsung' => 'nullable|numeric|min:0',
            'penerima_tidak_langsung' => 'nullable|numeric|min:0',
            'penerima_penunjang' => 'nullable|numeric|min:0',
            'sumber_pembiayaan' => 'nullable|string|max:50',
            'sifat_bantuan' => 'nullable|string|max:255',
            'lokasi' => 'nullable|string|max:255',
            'alokasi_anggaran' => 'nullable|numeric|min:0',
            'realisasi_sem1' => 'nullable|numeric|min:0',
            'realisasi_sem2' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'sub_kegiatan_id.required' => 'Sub Kegiatan harus dipilih',
            'sub_kegiatan_id.exists' => 'Sub Kegiatan tidak valid',
            'tahun.required' => 'Tahun harus diisi',
        ];
    }
}