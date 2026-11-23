<?php

namespace App\Services;

use App\Exceptions\HierarkiSampahException;
use App\Models\AuditLog;
use App\Models\HierarkiHapus;
use App\Models\Kegiatan;
use App\Models\Program;
use App\Models\SubKegiatan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hapus & kembalikan program / kegiatan / sub kegiatan dari halaman Input Data.
 *
 * Tidak ada baris yang benar-benar dihapus:
 *  - node (dan seluruh isinya yang masih aktif) → is_active = false + hapus_batch_id
 *  - laporan aktif di dalamnya → soft delete (deleted_at) + hapus_batch_id
 * Semua tampilan sudah menyaring is_active (hierarki) dan deleted_at (laporan),
 * jadi total di dashboard/rekap/export otomatis tidak menghitung data terhapus.
 *
 * "Kembalikan" memulihkan persis baris ber-hapus_batch_id itu.
 *
 * Pemulihan otomatis: bila ada data baru masuk ke item yang sedang di Data
 * Terhapus (mis. import RAT menulis laporan, atau sub kegiatan baru dibuat di
 * bawah kegiatan terhapus), item tsb diaktifkan kembali — data tidak boleh
 * terhitung di total tetapi tak terlihat di layar. Dipicu dari event model
 * LaporanOppkpke / SubKegiatan / Kegiatan.
 */
class HierarkiSampahService
{
    public const JENIS = ['program', 'kegiatan', 'sub_kegiatan'];

    private const TABEL = ['program' => 'programs', 'kegiatan' => 'kegiatan', 'sub_kegiatan' => 'sub_kegiatan'];

    private const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // ══════════════════════════════════════════════════════════════
    // BACA
    // ══════════════════════════════════════════════════════════════

    /**
     * Node AKTIF beserta perangkat daerah & lokasinya. Null bila tidak ada / sudah dihapus.
     *
     * @return array{jenis:string, model:\Illuminate\Database\Eloquent\Model, pd_id:?int, nama:string, lokasi:string}|null
     */
    public function node(string $jenis, int $id, bool $kunci = false): ?array
    {
        $query = match ($jenis) {
            'program'      => Program::query(),
            'kegiatan'     => Kegiatan::query(),
            'sub_kegiatan' => SubKegiatan::query(),
            default        => null,
        };
        if (! $query) {
            return null;
        }
        if ($kunci) {
            $query->lockForUpdate();
        }
        $m = $query->whereKey($id)->where('is_active', true)->first();
        if (! $m) {
            return null;
        }

        $kegiatan = $jenis === 'sub_kegiatan' ? $m->kegiatan : ($jenis === 'kegiatan' ? $m : null);
        $program  = $jenis === 'program' ? $m : $kegiatan?->program;
        $pdNama   = optional($program?->perangkatDaerah)->nama;

        $lokasi = collect([
            $pdNama,
            $jenis !== 'program' ? $program?->nama_program : null,
            $jenis === 'sub_kegiatan' ? $kegiatan?->nama_kegiatan : null,
        ])->filter()->implode(' › ');

        return [
            'jenis'  => $jenis,
            'model'  => $m,
            'pd_id'  => $program?->perangkat_daerah_id ? (int) $program->perangkat_daerah_id : null,
            'nama'   => (string) match ($jenis) {
                'program'      => $m->nama_program,
                'kegiatan'     => $m->nama_kegiatan,
                'sub_kegiatan' => $m->nama_sub_kegiatan,
            },
            'lokasi' => $lokasi,
        ];
    }

    /**
     * Isi aktif sebuah node: id kegiatan, id sub kegiatan, laporan aktif, ringkasan per tahun.
     *
     * @return array{kegiatan_ids:int[], sub_ids:int[], laporan:Collection, ringkasan:array}
     */
    public function isi(array $node): array
    {
        $id = (int) $node['model']->getKey();

        switch ($node['jenis']) {
            case 'program':
                $kegIds = Kegiatan::where('program_id', $id)->where('is_active', true)->pluck('id')->all();
                $subIds = $kegIds
                    ? SubKegiatan::whereIn('kegiatan_id', $kegIds)->where('is_active', true)->pluck('id')->all()
                    : [];
                break;
            case 'kegiatan':
                $kegIds = [$id];
                $subIds = SubKegiatan::where('kegiatan_id', $id)->where('is_active', true)->pluck('id')->all();
                break;
            default:
                $kegIds = [];
                $subIds = [$id];
        }

        $laporan = $subIds
            ? DB::table('laporan_oppkpke')->whereIn('sub_kegiatan_id', $subIds)->whereNull('deleted_at')
                ->orderBy('tahun')->orderBy('id')
                ->get(['id', 'sub_kegiatan_id', 'tahun', 'alokasi_anggaran', 'realisasi_total'])
            : collect();

        $ringkasan = $laporan->groupBy(fn ($l) => (int) $l->tahun)->map(fn ($g, $tahun) => [
            'tahun'     => (int) $tahun,
            'jumlah'    => $g->count(),
            'alokasi'   => round((float) $g->sum('alokasi_anggaran'), 2),
            'realisasi' => round((float) $g->sum('realisasi_total'), 2),
        ])->sortKeys()->values()->all();

        return [
            'kegiatan_ids' => array_map('intval', $kegIds),
            'sub_ids'      => array_map('intval', $subIds),
            'laporan'      => $laporan,
            'ringkasan'    => $ringkasan,
        ];
    }

    /** Ringkasan untuk jendela konfirmasi hapus. */
    public function info(string $jenis, int $id, User $user): array
    {
        $node = $this->node($jenis, $id);
        if (! $node) {
            throw new HierarkiSampahException('Data ini sudah tidak ada atau sudah dihapus. Silakan muat ulang halaman.', 404);
        }
        $this->pastikanBoleh($user, $node['pd_id']);
        $isi = $this->isi($node);

        return [
            'jenis'           => $jenis,
            'label'           => HierarkiHapus::LABEL[$jenis],
            'nama'            => $node['nama'],
            'lokasi'          => $node['lokasi'],
            'jumlah_kegiatan' => $jenis === 'program' ? count($isi['kegiatan_ids']) : 0,
            'jumlah_sub'      => $jenis === 'sub_kegiatan' ? 0 : count($isi['sub_ids']),
            'jumlah_laporan'  => $isi['laporan']->count(),
            'ringkasan'       => $isi['ringkasan'],
        ];
    }

    /**
     * Daftar Data Terhapus yang masih bisa dikembalikan.
     * Operator daerah: hanya perangkat daerahnya. Admin: semua (atau satu PD bila difilter).
     */
    public function daftar(User $user, ?int $pdFilter = null): array
    {
        $q = HierarkiHapus::with('perangkatDaerah:id,nama')->whereNull('dipulihkan_pada')->orderByDesc('id');
        if ($user->isDaerah()) {
            $q->where('perangkat_daerah_id', (int) ($user->perangkat_daerah_id ?: 0));
        } elseif ($pdFilter) {
            $q->where('perangkat_daerah_id', $pdFilter);
        }
        $batches = $q->limit(300)->get();
        if ($batches->isEmpty()) {
            return [];
        }

        // Sisa baris yang masih menunggu dikembalikan, per batch (4 query total).
        $ids  = $batches->pluck('id')->all();
        $sisa = [];
        foreach (array_merge(array_values(self::TABEL), ['laporan_oppkpke']) as $t) {
            $hit = DB::table($t)->whereIn('hapus_batch_id', $ids)
                ->selectRaw('hapus_batch_id, COUNT(*) as c')->groupBy('hapus_batch_id')->pluck('c', 'hapus_batch_id');
            foreach ($hit as $bid => $c) {
                $sisa[(int) $bid] = ($sisa[(int) $bid] ?? 0) + (int) $c;
            }
        }

        // Apakah node utamanya masih di Data Terhapus (bukan aktif kembali otomatis)?
        $akarTerhapus = [];
        foreach (self::TABEL as $jenis => $t) {
            $bJenis = $batches->where('jenis', $jenis);
            if ($bJenis->isEmpty()) {
                continue;
            }
            $map = DB::table($t)->whereIn('id', $bJenis->pluck('objek_id')->all())->pluck('hapus_batch_id', 'id');
            foreach ($bJenis as $b) {
                $akarTerhapus[$b->id] = (int) ($map[$b->objek_id] ?? 0) === (int) $b->id;
            }
        }

        $hasil = [];
        foreach ($batches as $b) {
            if (($sisa[$b->id] ?? 0) === 0) {
                // Semua isinya sudah aktif kembali (otomatis) → tutup.
                $b->update(['dipulihkan_pada' => now(), 'cara_pulih' => 'otomatis']);
                continue;
            }
            $hasil[] = [
                'id'              => $b->id,
                'jenis'           => $b->jenis,
                'label'           => $b->label_jenis,
                'nama'            => $b->nama,
                'lokasi'          => $b->lokasi,
                'pd_nama'         => optional($b->perangkatDaerah)->nama,
                'jumlah_kegiatan' => $b->jumlah_kegiatan,
                'jumlah_sub'      => $b->jumlah_sub,
                'jumlah_laporan'  => $b->jumlah_laporan,
                'ringkasan'       => $b->ringkasan ?: [],
                'dihapus_oleh'    => $b->dihapus_oleh_nama,
                'dihapus_pada'    => $this->waktu($b->created_at),
                'sebagian_aktif'  => ! ($akarTerhapus[$b->id] ?? true),
            ];
        }

        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════
    // HAPUS
    // ══════════════════════════════════════════════════════════════

    public function hapus(string $jenis, int $id, User $user): HierarkiHapus
    {
        $batch = DB::transaction(function () use ($jenis, $id, $user) {
            $node = $this->node($jenis, $id, true);
            if (! $node) {
                throw new HierarkiSampahException('Data ini sudah tidak ada atau sudah dihapus. Silakan muat ulang halaman.', 404);
            }
            $this->pastikanBoleh($user, $node['pd_id']);
            $isi = $this->isi($node);

            $batch = HierarkiHapus::create([
                'perangkat_daerah_id' => $node['pd_id'],
                'jenis'               => $jenis,
                'objek_id'            => $id,
                'nama'                => $node['nama'],
                'lokasi'              => $node['lokasi'],
                'jumlah_kegiatan'     => $jenis === 'program' ? count($isi['kegiatan_ids']) : 0,
                'jumlah_sub'          => $jenis === 'sub_kegiatan' ? 0 : count($isi['sub_ids']),
                'jumlah_laporan'      => $isi['laporan']->count(),
                'ringkasan'           => $isi['ringkasan'],
                'dihapus_oleh'        => $user->id,
                'dihapus_oleh_nama'   => $this->namaPengguna($user),
            ]);

            $tandai = ['is_active' => false, 'hapus_batch_id' => $batch->id];
            if ($jenis === 'program') {
                DB::table('programs')->where('id', $id)->update($tandai);
            }
            if ($isi['kegiatan_ids']) {
                DB::table('kegiatan')->whereIn('id', $isi['kegiatan_ids'])->update($tandai);
            }
            if ($isi['sub_ids']) {
                DB::table('sub_kegiatan')->whereIn('id', $isi['sub_ids'])->update($tandai);
            }

            // Unique (sub_kegiatan_id, tahun, deleted_at): bila (data lama) ada dua
            // laporan aktif untuk sub+tahun yang sama, beri detik berbeda.
            $now  = now();
            $urut = [];
            foreach ($isi['laporan'] as $l) {
                $k = $l->sub_kegiatan_id . '|' . $l->tahun;
                $urut[$k] = isset($urut[$k]) ? $urut[$k] + 1 : 0;
                DB::table('laporan_oppkpke')->where('id', $l->id)->whereNull('deleted_at')->update([
                    'deleted_at'     => $now->copy()->addSeconds($urut[$k]),
                    'hapus_batch_id' => $batch->id,
                ]);
            }

            AuditLog::record('hierarki.deleted',
                'Hapus ' . strtolower(HierarkiHapus::LABEL[$jenis]) . ' "' . $node['nama'] . '"'
                    . $this->rincian($batch) . ' — bisa dikembalikan dari Data Terhapus',
                $batch,
                [
                    'perangkat_daerah_id' => $node['pd_id'],
                    'jenis'               => $jenis,
                    'objek_id'            => $id,
                    'kegiatan_ids'        => $isi['kegiatan_ids'],
                    'sub_ids'             => $isi['sub_ids'],
                    'laporan_ids'         => $isi['laporan']->pluck('id')->all(),
                    'ringkasan'           => $isi['ringkasan'],
                ]);

            return $batch;
        });

        $this->lupakanCache($batch->ringkasan ?: []);

        return $batch;
    }

    // ══════════════════════════════════════════════════════════════
    // KEMBALIKAN
    // ══════════════════════════════════════════════════════════════

    /** @return array{batch:HierarkiHapus, pulih:int, lewat:int[]} */
    public function kembalikan(int $batchId, User $user): array
    {
        $hasil = DB::transaction(function () use ($batchId, $user) {
            $b = HierarkiHapus::whereKey($batchId)->lockForUpdate()->first();
            if (! $b) {
                throw new HierarkiSampahException('Data terhapus ini tidak ditemukan. Silakan muat ulang halaman.', 404);
            }
            $this->pastikanBoleh($user, $b->perangkat_daerah_id);
            if ($b->dipulihkan_pada) {
                throw new HierarkiSampahException('Data ini sudah dikembalikan sebelumnya.', 409);
            }
            $this->pastikanIndukAktif($b);

            foreach (self::TABEL as $t) {
                DB::table($t)->where('hapus_batch_id', $b->id)->update(['is_active' => true, 'hapus_batch_id' => null]);
            }

            $pulih = 0;
            $lewat = [];
            $rows = DB::table('laporan_oppkpke')->where('hapus_batch_id', $b->id)->orderBy('id')
                ->get(['id', 'sub_kegiatan_id', 'tahun', 'deleted_at']);
            foreach ($rows as $r) {
                if ($r->deleted_at === null) {
                    DB::table('laporan_oppkpke')->where('id', $r->id)->update(['hapus_batch_id' => null]);
                    continue;
                }
                // Sudah ada laporan baru untuk sub+tahun yang sama → jangan dobel; yang lama tetap terhapus.
                $sudahAda = DB::table('laporan_oppkpke')->where('sub_kegiatan_id', $r->sub_kegiatan_id)
                    ->where('tahun', $r->tahun)->whereNull('deleted_at')->exists();
                if ($sudahAda) {
                    DB::table('laporan_oppkpke')->where('id', $r->id)->update(['hapus_batch_id' => null]);
                    $lewat[] = (int) $r->tahun;
                    continue;
                }
                DB::table('laporan_oppkpke')->where('id', $r->id)->update(['deleted_at' => null, 'hapus_batch_id' => null]);
                $pulih++;
            }

            $b->update([
                'dipulihkan_pada'      => now(),
                'dipulihkan_oleh'      => $user->id,
                'dipulihkan_oleh_nama' => $this->namaPengguna($user),
                'cara_pulih'           => 'manual',
            ]);

            AuditLog::record('hierarki.restored',
                'Kembalikan ' . strtolower($b->label_jenis) . ' "' . $b->nama . '" dari Data Terhapus',
                $b,
                [
                    'perangkat_daerah_id' => $b->perangkat_daerah_id,
                    'jenis'               => $b->jenis,
                    'objek_id'            => $b->objek_id,
                    'laporan_dipulihkan'  => $pulih,
                    'laporan_dilewati'    => count($lewat),
                ]);

            return ['batch' => $b, 'pulih' => $pulih, 'lewat' => $lewat];
        });

        $this->lupakanCache($hasil['batch']->ringkasan ?: []);

        return $hasil;
    }

    // ══════════════════════════════════════════════════════════════
    // PEMULIHAN OTOMATIS (dipanggil dari event model)
    // ══════════════════════════════════════════════════════════════

    /** Laporan aktif akan ditulis ke sub kegiatan ini → pastikan sub & induknya aktif. */
    public static function aktifkanBilaTerhapus(int $subId): void
    {
        $sk = DB::table('sub_kegiatan')->where('id', $subId)->first(['id', 'is_active', 'hapus_batch_id', 'kegiatan_id']);
        if (! $sk) {
            return;
        }
        if (! $sk->is_active && $sk->hapus_batch_id) {
            DB::table('sub_kegiatan')->where('id', $sk->id)->update(['is_active' => true, 'hapus_batch_id' => null]);
            static::catatOtomatis('sub_kegiatan', (int) $sk->id, (int) $sk->hapus_batch_id);
        }
        if (! $sk->is_active && ! $sk->hapus_batch_id) {
            return;   // non-aktif lama (bukan dari Data Terhapus) — tidak disentuh
        }
        static::aktifkanIndukBilaTerhapus((int) $sk->kegiatan_id);
    }

    /** Ada sub kegiatan / kegiatan aktif di bawahnya → kegiatan & program induk tak boleh terhapus. */
    public static function aktifkanIndukBilaTerhapus(int $kegiatanId): void
    {
        $k = DB::table('kegiatan')->where('id', $kegiatanId)->first(['id', 'is_active', 'hapus_batch_id', 'program_id']);
        if (! $k) {
            return;
        }
        if (! $k->is_active && $k->hapus_batch_id) {
            DB::table('kegiatan')->where('id', $k->id)->update(['is_active' => true, 'hapus_batch_id' => null]);
            static::catatOtomatis('kegiatan', (int) $k->id, (int) $k->hapus_batch_id);
        }
        static::aktifkanProgramBilaTerhapus((int) $k->program_id);
    }

    public static function aktifkanProgramBilaTerhapus(int $programId): void
    {
        $p = DB::table('programs')->where('id', $programId)->first(['id', 'is_active', 'hapus_batch_id']);
        if ($p && ! $p->is_active && $p->hapus_batch_id) {
            DB::table('programs')->where('id', $p->id)->update(['is_active' => true, 'hapus_batch_id' => null]);
            static::catatOtomatis('program', (int) $p->id, (int) $p->hapus_batch_id);
        }
    }

    private static function catatOtomatis(string $jenis, int $id, int $batchId): void
    {
        AuditLog::record('hierarki.restored_auto',
            HierarkiHapus::LABEL[$jenis] . " #{$id} aktif kembali otomatis karena ada data baru masuk (Data Terhapus #{$batchId})",
            null,
            ['jenis' => $jenis, 'objek_id' => $id, 'hapus_batch_id' => $batchId]);
    }

    // ══════════════════════════════════════════════════════════════
    // BANTUAN
    // ══════════════════════════════════════════════════════════════

    private function pastikanBoleh(User $user, ?int $pdId): void
    {
        if ($user->isMaster() || $user->isItTeam()) {
            return;
        }
        if ($user->isDaerah() && $user->perangkat_daerah_id && $pdId && (int) $user->perangkat_daerah_id === (int) $pdId) {
            return;
        }
        throw new HierarkiSampahException('Data ini bukan milik perangkat daerah Anda.', 403);
    }

    /** Induk harus aktif dulu; kalau ikut terhapus, kembalikan induknya lebih dulu. */
    private function pastikanIndukAktif(HierarkiHapus $b): void
    {
        if ($b->jenis === 'program') {
            return;
        }

        $kegiatan = $b->jenis === 'kegiatan'
            ? Kegiatan::find($b->objek_id)
            : optional(SubKegiatan::find($b->objek_id))->kegiatan;
        if (! $kegiatan) {
            throw new HierarkiSampahException('Data ini tidak bisa dikembalikan karena induknya sudah tidak ada. Silakan hubungi Tim IT lewat Chat Support.', 422);
        }

        $program = $kegiatan->program;
        if ($program && ! $program->is_active) {
            throw new HierarkiSampahException(
                'Data ini berada di dalam program "' . $program->nama_program . '" yang juga ada di Data Terhapus. '
                . 'Kembalikan program tersebut terlebih dahulu.', 422);
        }
        if ($b->jenis === 'sub_kegiatan' && ! $kegiatan->is_active) {
            throw new HierarkiSampahException(
                'Data ini berada di dalam kegiatan "' . $kegiatan->nama_kegiatan . '" yang juga ada di Data Terhapus. '
                . 'Kembalikan kegiatan tersebut terlebih dahulu.', 422);
        }
    }

    private function rincian(HierarkiHapus $b): string
    {
        $bagian = [];
        if ($b->jumlah_kegiatan) {
            $bagian[] = $b->jumlah_kegiatan . ' kegiatan';
        }
        if ($b->jumlah_sub) {
            $bagian[] = $b->jumlah_sub . ' sub kegiatan';
        }
        if ($b->jumlah_laporan) {
            $bagian[] = $b->jumlah_laporan . ' laporan';
        }

        return $bagian ? ' (beserta ' . implode(', ', $bagian) . ')' : '';
    }

    private function namaPengguna(User $user): string
    {
        return (string) ($user->nama_lengkap ?: $user->name);
    }

    private function waktu(?CarbonInterface $t): ?string
    {
        if (! $t) {
            return null;
        }
        $w = $t->copy()->setTimezone('Asia/Makassar');

        return $w->format('j') . ' ' . self::BULAN[(int) $w->format('n')] . ' ' . $w->format('Y') . ', pukul ' . $w->format('H.i') . ' WITA';
    }

    private function lupakanCache(array $ringkasan): void
    {
        foreach ($ringkasan as $r) {
            if (isset($r['tahun'])) {
                Cache::forget('insight:snapshot:' . (int) $r['tahun']);
            }
        }
    }
}
