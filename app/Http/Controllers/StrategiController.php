<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StrategiOppkpke;
use Illuminate\Http\Request;

/**
 * Kelola label (nama/deskripsi) Strategi OPPKPKE — Admin Master & Tim IT.
 * Kode strategi tidak diubah (dipakai untuk pencocokan/relasi).
 */
class StrategiController extends Controller
{
    public function index()
    {
        $strategis = StrategiOppkpke::orderBy('kode')->get();

        return view('admin.strategi', compact('strategis'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nama'      => 'required|string|max:255',
            'deskripsi' => 'nullable|string|max:1000',
        ], [
            'nama.required' => 'Nama strategi wajib diisi.',
        ]);

        $strategi = StrategiOppkpke::findOrFail($id);

        $nama = trim($data['nama']);

        // Cegah label kembar antar strategi (membingungkan pencocokan Import RAT).
        $dupe = StrategiOppkpke::where('id', '!=', $strategi->id)
            ->whereRaw('LOWER(TRIM(nama)) = ?', [mb_strtolower($nama)])
            ->exists();
        if ($dupe) {
            return response()->json([
                'success' => false,
                'message' => 'Sudah ada strategi lain dengan nama yang sama.',
            ], 422);
        }

        $lama = $strategi->nama;
        $strategi->nama = $nama;
        if ($request->exists('deskripsi')) {
            $strategi->deskripsi = ($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;
        }
        $strategi->save();

        AuditLog::record('strategi.updated', 'Ubah label strategi [' . $strategi->kode . '] menjadi "' . $nama . '"', $strategi, [
            'kode'      => $strategi->kode,
            'nama_lama' => $lama,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Label strategi diperbarui.',
            'nama'    => $strategi->nama,
        ]);
    }
}
