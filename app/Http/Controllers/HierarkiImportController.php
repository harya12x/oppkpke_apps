<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\HierarkiImportService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/**
 * Import HIERARKI via Excel (Admin Master & Tim IT).
 * Perangkat Daerah → Strategi → Program → Kegiatan → Sub Kegiatan.
 * Selalu: unduh template → upload → PREVIEW → eksekusi.
 */
class HierarkiImportController extends Controller
{
    public function __construct(private HierarkiImportService $service) {}

    public function page()
    {
        return view('oppkpke.import-hierarki');
    }

    /** Unduh template .xlsx. */
    public function template()
    {
        $spreadsheet = $this->service->buildTemplate();

        return response()->streamDownload(function () use ($spreadsheet) {
            (new XlsxWriter($spreadsheet))->save('php://output');
        }, 'Template_Import_Hierarki_OPPKPKE.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Baca + validasi + tampilkan preview (belum menulis DB). */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|extensions:csv,xlsx,xls|max:20480',
        ], [
            'file.required'   => 'Pilih file terlebih dahulu.',
            'file.extensions' => 'Format harus Excel (.xlsx/.xls) atau CSV.',
            'file.max'        => 'Ukuran file maksimal 20MB.',
        ]);

        $file = $request->file('file');

        try {
            $rawRows = $this->service->readFile($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file: ' . $e->getMessage()])->withInput();
        }

        if (empty($rawRows)) {
            return back()->withErrors(['file' => 'Tidak ada data. Pastikan mengisi mulai baris ke-2 sesuai template.'])->withInput();
        }

        $result   = $this->service->analyze($rawRows);
        $cacheKey = 'hierarki_import_' . auth()->id() . '_' . uniqid();
        cache()->put($cacheKey, ['rows' => $result['rows']], now()->addHours(2));

        return view('oppkpke.import-hierarki', [
            'rows'     => $result['rows'],
            'stats'    => $result['stats'],
            'cacheKey' => $cacheKey,
        ]);
    }

    /** Eksekusi baris valid dari hasil preview. */
    public function execute(Request $request)
    {
        $request->validate(['cache_key' => 'required|string']);

        // Idempotency: ambil + hapus cache SEBELUM proses (cegah double-submit).
        $cached = cache()->pull($request->cache_key);
        if (! $cached) {
            return redirect()->route('oppkpke.import.hierarki')
                ->withErrors(['error' => 'Sesi preview kedaluwarsa atau sudah diproses. Silakan upload ulang.']);
        }

        try {
            $summary = $this->service->execute($cached['rows']);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('oppkpke.import.hierarki')
                ->withErrors(['error' => 'Gagal menjalankan import: ' . $e->getMessage() . '. Tidak ada data yang tersimpan (dibatalkan otomatis).']);
        }

        AuditLog::record('hierarki.imported', 'Import hierarki via Excel', null, $summary);

        return redirect()->route('oppkpke.import.hierarki')->with('import_result', $summary);
    }
}
