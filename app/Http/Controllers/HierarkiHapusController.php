<?php

namespace App\Http\Controllers;

use App\Exceptions\HierarkiSampahException;
use App\Services\HierarkiSampahService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HAPUS & KEMBALIKAN program / kegiatan / sub kegiatan — dari halaman Input Data.
 *
 * Akses: Operator Daerah (hanya perangkat daerahnya), Admin Master, Tim IT.
 * Logika & aturan ada di HierarkiSampahService.
 */
class HierarkiHapusController extends Controller
{
    public function __construct(private HierarkiSampahService $sampah) {}

    /** Ringkasan isi sebelum menghapus (untuk jendela konfirmasi). */
    public function info(string $jenis, int $id): JsonResponse
    {
        return $this->jalankan(fn () => [
            'success' => true,
            'data'    => $this->sampah->info($this->jenis($jenis), $id, auth()->user()),
        ]);
    }

    public function destroy(string $jenis, int $id): JsonResponse
    {
        return $this->jalankan(function () use ($jenis, $id) {
            $batch = $this->sampah->hapus($this->jenis($jenis), $id, auth()->user());

            return [
                'success'  => true,
                'message'  => $batch->label_jenis . ' "' . $batch->nama . '" sudah dihapus. Data disimpan di Data Terhapus dan bisa dikembalikan.',
                'batch_id' => $batch->id,
            ];
        });
    }

    /** Daftar Data Terhapus. */
    public function index(Request $request): JsonResponse
    {
        $pd = $request->filled('perangkat_daerah_id') ? (int) $request->perangkat_daerah_id : null;

        return $this->jalankan(fn () => [
            'success' => true,
            'data'    => $this->sampah->daftar(auth()->user(), $pd),
        ]);
    }

    public function restore(int $batch): JsonResponse
    {
        return $this->jalankan(function () use ($batch) {
            $r = $this->sampah->kembalikan($batch, auth()->user());
            $pesan = $r['batch']->label_jenis . ' "' . $r['batch']->nama . '" sudah dikembalikan.';
            if ($r['lewat']) {
                $pesan .= ' ' . count($r['lewat']) . ' laporan lama (TA ' . implode(', ', array_unique($r['lewat']))
                    . ') tidak dikembalikan karena sudah ada laporan baru untuk tahun yang sama.';
            }

            return ['success' => true, 'message' => $pesan, 'dilewati' => count($r['lewat'])];
        });
    }

    private function jenis(string $jenis): string
    {
        return $jenis === 'sub-kegiatan' ? 'sub_kegiatan' : $jenis;
    }

    private function jalankan(callable $aksi): JsonResponse
    {
        try {
            return response()->json($aksi());
        } catch (HierarkiSampahException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status());
        } catch (\Throwable $e) {
            report($e);
            Log::channel('audit')->error('Hapus/kembalikan hierarki gagal', ['user_id' => auth()->id(), 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem. Tidak ada data yang berubah. Silakan coba lagi.'], 500);
        }
    }
}
