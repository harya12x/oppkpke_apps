<?php

namespace App\Http\Controllers;

use App\Models\PerangkatDaerah;
use App\Services\PerangkatDaerahMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kelola Perangkat Daerah — khusus Tim IT (Master super-admin tetap bisa akses).
 * Fokus: mendeteksi & menggabungkan (merge) PD duplikat hasil import.
 */
class PerangkatDaerahController extends Controller
{
    public function __construct(private PerangkatDaerahMergeService $mergeService) {}

    public function index()
    {
        $all = PerangkatDaerah::orderBy('nama')->get(['id', 'kode', 'nama', 'singkatan', 'jenis', 'is_active']);

        // Hitungan per PD (satu query masing-masing, di-key by pd_id).
        $programCounts = DB::table('programs')
            ->select('perangkat_daerah_id', DB::raw('COUNT(*) as c'))
            ->groupBy('perangkat_daerah_id')->pluck('c', 'perangkat_daerah_id');

        $operatorCounts = DB::table('users')
            ->where('role', 'daerah')->whereNotNull('perangkat_daerah_id')
            ->select('perangkat_daerah_id', DB::raw('COUNT(*) as c'))
            ->groupBy('perangkat_daerah_id')->pluck('c', 'perangkat_daerah_id');

        // Jumlah laporan aktif (exclude soft-deleted) per PD via join ke programs.
        $laporanCounts = DB::table('laporan_oppkpke as lo')
            ->join('sub_kegiatan as sk', 'lo.sub_kegiatan_id', '=', 'sk.id')
            ->join('kegiatan as k', 'sk.kegiatan_id', '=', 'k.id')
            ->join('programs as p', 'k.program_id', '=', 'p.id')
            ->whereNull('lo.deleted_at')
            ->select('p.perangkat_daerah_id', DB::raw('COUNT(*) as c'))
            ->groupBy('p.perangkat_daerah_id')->pluck('c', 'perangkat_daerah_id');

        $all->each(function ($pd) use ($programCounts, $operatorCounts, $laporanCounts) {
            $pd->program_count  = (int) ($programCounts[$pd->id]  ?? 0);
            $pd->operator_count = (int) ($operatorCounts[$pd->id] ?? 0);
            $pd->laporan_count  = (int) ($laporanCounts[$pd->id]  ?? 0);
        });

        $duplicateGroups = $this->mergeService->duplicateGroups($all);

        return view('oppkpke.perangkat-daerah', compact('all', 'duplicateGroups'));
    }

    public function merge(Request $request)
    {
        $validated = $request->validate([
            'target_id'     => 'required|integer|exists:perangkat_daerah,id',
            'source_ids'    => 'required|array|min:1|max:20',
            'source_ids.*'  => 'integer|distinct|exists:perangkat_daerah,id|different:target_id',
        ], [
            'source_ids.required'   => 'Pilih minimal satu perangkat daerah untuk digabung.',
            'source_ids.*.different' => 'Perangkat daerah sumber tidak boleh sama dengan tujuan.',
        ]);

        $target  = PerangkatDaerah::findOrFail($validated['target_id']);
        $results = [];

        try {
            foreach ($validated['source_ids'] as $sourceId) {
                $source    = PerangkatDaerah::findOrFail($sourceId);
                $results[] = $this->mergeService->merge($source, $target);
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menggabungkan perangkat daerah. Semua perubahan dibatalkan (rollback).',
            ], 500);
        }

        $totalPrograms  = array_sum(array_column($results, 'programs_moved')) + array_sum(array_column($results, 'programs_merged'));
        $totalOps       = array_sum(array_map(fn ($r) => count($r['operators_deactivated']), $results));

        return response()->json([
            'success' => true,
            'message' => count($results) . " perangkat daerah digabung ke <strong>{$target->nama}</strong> "
                       . "({$totalPrograms} program dipindah, {$totalOps} operator dinonaktifkan).",
            'results' => $results,
        ]);
    }
}
