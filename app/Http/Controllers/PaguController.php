<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LaporanOppkpke;
use App\Models\PerangkatDaerah;
use App\Models\SubKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KELOLA PAGU ANGGARAN — satu halaman khusus untuk mengubah pagu (alokasi
 * anggaran) banyak sub kegiatan sekaligus.
 *
 * Alasan dipisah dari "Input Laporan": operator sering hanya perlu memutakhirkan
 * angka pagu (mis. setelah pergeseran/APBD Perubahan) tanpa menyentuh 20 kolom
 * lainnya. Di sini mereka mengetik angka pada daftar, melihat langsung sisa &
 * serapannya, lalu menyimpan sekali jalan.
 *
 * Akses: Operator Daerah (terkunci pada perangkat daerahnya) dan Admin Master
 * (semua perangkat daerah, wajib memilih salah satu agar daftarnya terfokus).
 */
class PaguController extends Controller
{
    /** Batas jumlah baris yang ditampilkan/disimpan sekali jalan. */
    private const PER_PAGE = 50;

    public function index(Request $request)
    {
        $user  = auth()->user();
        $tahun = (int) $request->input('tahun', date('Y'));

        // Operator daerah selalu terkunci ke perangkat daerahnya sendiri.
        $pdTerkunci = $user->isDaerah() ? $user->perangkat_daerah_id : null;
        $pdId       = $pdTerkunci ?: ($request->filled('perangkat_daerah_id') ? (int) $request->perangkat_daerah_id : null);

        $daftarPd = PerangkatDaerah::where('is_active', true)
            ->when($pdTerkunci, fn ($q) => $q->where('id', $pdTerkunci))
            ->orderBy('nama')->get(['id', 'nama']);

        $tahunTersedia = LaporanOppkpke::query()
            ->select('tahun')->distinct()->orderByDesc('tahun')->pluck('tahun')->all();
        if (! in_array($tahun, $tahunTersedia, true)) {
            array_unshift($tahunTersedia, $tahun);
        }

        $rows      = collect();
        $ringkasan = ['jumlah' => 0, 'terisi' => 0, 'kosong' => 0, 'pagu' => 0.0, 'realisasi' => 0.0];
        $paginator = null;

        if ($pdId) {
            $query = $this->baseQuery($tahun, $pdId, $request);

            // Ringkasan dihitung dari SELURUH hasil filter, bukan hanya halaman ini.
            $ringkasan = $this->hitungRingkasan($tahun, $pdId, $request);

            $paginator = $query
                ->orderBy('programs.kode_program')
                ->orderBy('programs.nama_program')
                ->orderBy('kegiatan.nama_kegiatan')
                ->orderBy('sub_kegiatan.nama_sub_kegiatan')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

            $rows = collect($paginator->items())->map(fn ($r) => [
                'id'         => (int) $r->id,
                'kode'       => (string) ($r->kode ?? ''),
                'nama'       => (string) $r->nama_sub_kegiatan,
                'program'    => trim((string) $r->nama_program),
                'kode_prog'  => trim((string) $r->kode_program),
                'kegiatan'   => trim((string) $r->nama_kegiatan),
                'strategi'   => trim((string) ($r->nama_strategi ?? '')),
                'pagu'       => (float) ($r->alokasi_anggaran ?? 0),
                'realisasi'  => (float) ($r->realisasi_total ?? 0),
                'ada_lapor'  => $r->laporan_id !== null,
            ]);
        }

        return view('oppkpke.pagu', [
            'tahun'         => $tahun,
            'tahunTersedia' => $tahunTersedia,
            'daftarPd'      => $daftarPd,
            'pdId'          => $pdId,
            'pdTerkunci'    => (bool) $pdTerkunci,
            'rows'          => $rows,
            'paginator'     => $paginator,
            'ringkasan'     => $ringkasan,
        ]);
    }

    /**
     * Simpan banyak perubahan pagu sekaligus.
     *
     * Hanya kolom alokasi_anggaran yang disentuh — kolom laporan lain (realisasi,
     * sasaran, lokasi, dst.) sengaja tidak ikut ditulis agar pekerjaan operator
     * di halaman Input Laporan tidak tertimpa.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun'             => 'required|integer|min:2000|max:2100',
            'items'             => 'required|array|min:1|max:' . self::PER_PAGE,
            'items.*.id'        => 'required|integer',
            'items.*.pagu'      => 'required|numeric|min:0|max:99999999999999',
        ], [
            'items.required' => 'Tidak ada perubahan untuk disimpan.',
            'items.max'      => 'Terlalu banyak baris sekaligus — simpan per halaman.',
            'items.*.pagu.numeric' => 'Nilai pagu harus berupa angka.',
            'items.*.pagu.max'     => 'Nilai pagu terlalu besar.',
        ]);

        $user  = auth()->user();
        $tahun = (int) $data['tahun'];
        $ids   = array_values(array_unique(array_map(fn ($i) => (int) $i['id'], $data['items'])));

        // Kunci kepemilikan: operator daerah hanya boleh menyentuh sub kegiatan
        // milik perangkat daerahnya. Divalidasi di server, bukan sekadar di UI.
        $sahQuery = SubKegiatan::whereIn('sub_kegiatan.id', $ids);
        if ($user->isDaerah()) {
            if (! $user->perangkat_daerah_id) {
                return response()->json(['success' => false, 'message' => 'Akun Anda belum terhubung ke perangkat daerah.'], 403);
            }
            $sahQuery->whereHas('kegiatan.program', fn ($q) => $q->where('perangkat_daerah_id', $user->perangkat_daerah_id));
        }
        $idSah = $sahQuery->pluck('sub_kegiatan.id')->all();

        if (count($idSah) !== count($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Ada baris yang bukan milik perangkat daerah Anda — perubahan dibatalkan.',
            ], 403);
        }

        $perubahan = [];
        $hasil     = [];

        DB::transaction(function () use ($data, $tahun, &$perubahan, &$hasil) {
            foreach ($data['items'] as $item) {
                $subId = (int) $item['id'];
                $baru  = round((float) $item['pagu'], 2);

                $laporan = LaporanOppkpke::firstOrNew([
                    'sub_kegiatan_id' => $subId,
                    'tahun'           => $tahun,
                ]);

                $lama = (float) ($laporan->alokasi_anggaran ?? 0);
                if ($laporan->exists && abs($lama - $baru) < 0.005) {
                    $hasil[$subId] = ['pagu' => $lama, 'realisasi' => (float) $laporan->realisasi_total, 'berubah' => false];
                    continue;
                }

                if (! $laporan->exists) {
                    // Baris laporan baru: isi nilai minimal yang aman.
                    $laporan->semester        = $laporan->semester ?? 2;
                    $laporan->realisasi_sem1  = $laporan->realisasi_sem1 ?? 0;
                    $laporan->realisasi_sem2  = $laporan->realisasi_sem2 ?? 0;
                    $laporan->realisasi_total = $laporan->realisasi_total ?? 0;
                    $laporan->created_by      = auth()->id();
                }

                $laporan->alokasi_anggaran = $baru;
                $laporan->updated_by       = auth()->id();
                $laporan->save();

                $perubahan[] = ['sub_kegiatan_id' => $subId, 'lama' => $lama, 'baru' => $baru];
                $hasil[$subId] = [
                    'pagu'      => $baru,
                    'realisasi' => (float) $laporan->realisasi_total,
                    'berubah'   => true,
                ];
            }
        });

        if ($perubahan) {
            $selisih = array_sum(array_map(fn ($p) => $p['baru'] - $p['lama'], $perubahan));
            AuditLog::record(
                'pagu.updated',
                'Ubah pagu anggaran ' . count($perubahan) . ' sub kegiatan TA ' . $tahun
                    . ' (selisih ' . ($selisih >= 0 ? '+' : '') . number_format($selisih, 0, ',', '.') . ')',
                null,
                [
                    'tahun'               => $tahun,
                    'jumlah_baris'        => count($perubahan),
                    'selisih_total'       => $selisih,
                    'perangkat_daerah_id' => $user->perangkat_daerah_id,
                    // Rinci dibatasi agar baris audit tidak membengkak.
                    'perubahan'           => array_slice($perubahan, 0, 100),
                ],
            );
        }

        $ringkasan = $this->hitungRingkasan(
            $tahun,
            $user->isDaerah() ? (int) $user->perangkat_daerah_id : ($request->filled('perangkat_daerah_id') ? (int) $request->perangkat_daerah_id : null),
            $request
        );

        return response()->json([
            'success'   => true,
            'message'   => $perubahan
                ? count($perubahan) . ' pagu berhasil disimpan.'
                : 'Tidak ada nilai yang berubah.',
            'tersimpan' => count($perubahan),
            'hasil'     => $hasil,
            'ringkasan' => $ringkasan,
        ]);
    }

    /**
     * Daftar sub kegiatan satu perangkat daerah + laporan tahun berjalan.
     * Dipakai bersama oleh daftar berhalaman dan perhitungan ringkasan.
     */
    private function baseQuery(int $tahun, int $pdId, Request $request, bool $denganKolom = true)
    {
        $query = SubKegiatan::query()
            ->join('kegiatan', 'sub_kegiatan.kegiatan_id', '=', 'kegiatan.id')
            ->join('programs', 'kegiatan.program_id', '=', 'programs.id')
            ->leftJoin('strategi_oppkpke', 'programs.strategi_id', '=', 'strategi_oppkpke.id')
            ->leftJoin('laporan_oppkpke', function ($join) use ($tahun) {
                $join->on('laporan_oppkpke.sub_kegiatan_id', '=', 'sub_kegiatan.id')
                     ->where('laporan_oppkpke.tahun', '=', $tahun)
                     ->whereNull('laporan_oppkpke.deleted_at');
            })
            ->where('sub_kegiatan.is_active', true)
            ->where('programs.perangkat_daerah_id', $pdId);

        if ($denganKolom) {
            $query->select([
                'sub_kegiatan.id',
                'sub_kegiatan.kode',
                'sub_kegiatan.nama_sub_kegiatan',
                'kegiatan.nama_kegiatan',
                'programs.kode_program',
                'programs.nama_program',
                'strategi_oppkpke.nama as nama_strategi',
                'laporan_oppkpke.id as laporan_id',
                'laporan_oppkpke.alokasi_anggaran',
                'laporan_oppkpke.realisasi_total',
            ]);
        }

        if ($request->filled('q')) {
            $cari = trim((string) $request->q);
            $query->where(function ($q) use ($cari) {
                $q->where('sub_kegiatan.nama_sub_kegiatan', 'like', "%{$cari}%")
                  ->orWhere('sub_kegiatan.kode', 'like', "%{$cari}%")
                  ->orWhere('programs.nama_program', 'like', "%{$cari}%")
                  ->orWhere('kegiatan.nama_kegiatan', 'like', "%{$cari}%");
            });
        }

        if ($request->input('status') === 'terisi') {
            $query->where('laporan_oppkpke.alokasi_anggaran', '>', 0);
        } elseif ($request->input('status') === 'kosong') {
            $query->where(function ($q) {
                $q->whereNull('laporan_oppkpke.id')->orWhere('laporan_oppkpke.alokasi_anggaran', '<=', 0);
            });
        }

        if ($request->filled('strategi_id')) {
            $query->where('programs.strategi_id', (int) $request->strategi_id);
        }

        return $query;
    }

    /** @return array<string, float|int> */
    private function hitungRingkasan(int $tahun, ?int $pdId, Request $request): array
    {
        if (! $pdId) {
            return ['jumlah' => 0, 'terisi' => 0, 'kosong' => 0, 'pagu' => 0.0, 'realisasi' => 0.0];
        }

        $row = $this->baseQuery($tahun, $pdId, $request, false)
            ->reorder()
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('SUM(CASE WHEN laporan_oppkpke.alokasi_anggaran > 0 THEN 1 ELSE 0 END) as terisi')
            ->selectRaw('COALESCE(SUM(laporan_oppkpke.alokasi_anggaran), 0) as pagu')
            ->selectRaw('COALESCE(SUM(laporan_oppkpke.realisasi_total), 0) as realisasi')
            ->first();

        $jumlah = (int) ($row->jumlah ?? 0);
        $terisi = (int) ($row->terisi ?? 0);

        return [
            'jumlah'    => $jumlah,
            'terisi'    => $terisi,
            'kosong'    => max($jumlah - $terisi, 0),
            'pagu'      => (float) ($row->pagu ?? 0),
            'realisasi' => (float) ($row->realisasi ?? 0),
        ];
    }
}
