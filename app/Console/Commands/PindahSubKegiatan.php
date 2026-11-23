<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\LaporanOppkpke;
use App\Models\SubKegiatan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Merapikan hierarki yang salah tempat: pindahkan sub kegiatan (beserta laporannya,
 * semua tahun) ke kegiatan lain di perangkat daerah yang sama, lalu opsional hapus
 * kegiatan/program asal yang menjadi kosong.
 *
 * Kenapa lewat command ini, bukan DELETE langsung: FK program -> kegiatan →
 * sub_kegiatan -> laporan_oppkpke memakai ON DELETE CASCADE. Menghapus program yang
 * salah label ikut menghapus laporan di bawahnya tanpa peringatan.
 *
 * Sub kegiatan bernama sama di kegiatan tujuan -> digabung. Bila keduanya punya
 * laporan di tahun yang sama, bawaannya DITOLAK; --bentrok=asal|tujuan memilih
 * laporan mana yang dipakai. Kolom kosong di laporan yang dipakai (mis. realisasi)
 * diisi dari laporan yang dibuang; laporan yang dibuang di-soft-delete (bisa dipulihkan).
 *
 * Bawaan SIMULASI: semua langkah dijalankan di dalam transaksi, total diperiksa, lalu
 * di-rollback. Hasil simulasi = hasil eksekusi. Tambah --eksekusi untuk menyimpan.
 *
 *   php artisan oppkpke:pindah-sub-kegiatan 173 174 --ke=6
 *   php artisan oppkpke:pindah-sub-kegiatan 176 --ke=161 --bentrok=asal --hapus-asal-kosong --eksekusi
 */
class PindahSubKegiatan extends Command
{
    protected $signature = 'oppkpke:pindah-sub-kegiatan
        {sub_kegiatan* : ID sub kegiatan yang dipindah}
        {--ke= : ID kegiatan tujuan}
        {--bentrok=tolak : Bila sama-sama punya laporan di tahun yang sama: tolak | asal | tujuan (laporan mana yang dipakai)}
        {--hapus-asal-kosong : Hapus kegiatan/program asal yang menjadi kosong}
        {--eksekusi : Simpan perubahan (tanpa opsi ini hanya simulasi)}';

    protected $description = 'Pindahkan sub kegiatan (beserta laporannya) ke kegiatan lain dalam perangkat daerah yang sama, lalu rapikan kegiatan/program asal yang kosong';

    /** Kolom laporan yang diisi dari laporan yang dibuang bila di laporan yang dipakai kosong. */
    private const KOLOM_ISI = [
        'alokasi_anggaran', 'realisasi_sem1', 'realisasi_sem2',
        'aktivitas_langsung', 'aktivitas_tidak_langsung', 'aktivitas_penunjang',
        'penerima_langsung', 'penerima_tidak_langsung', 'penerima_penunjang',
        'jumlah_sasaran', 'satuan_sasaran', 'besaran_manfaat', 'jenis_bantuan',
        'durasi_pemberian', 'sumber_pembiayaan', 'sifat_bantuan', 'lokasi',
    ];

    private const KOLOM_UANG = ['alokasi_anggaran', 'realisasi_sem1', 'realisasi_sem2'];

    private const PELAKSANA = 'artisan oppkpke:pindah-sub-kegiatan';

    public function handle(): int
    {
        $eksekusi = (bool) $this->option('eksekusi');
        $mode     = strtolower((string) $this->option('bentrok'));
        if (! in_array($mode, ['tolak', 'asal', 'tujuan'], true)) {
            $this->error('--bentrok harus salah satu dari: tolak, asal, tujuan.');
            return self::FAILURE;
        }

        $target = Kegiatan::with('program.perangkatDaerah', 'program.strategi')->find((int) $this->option('ke'));
        if (! $target || ! $target->program) {
            $this->error('Kegiatan tujuan (--ke=ID) tidak ditemukan.');
            return self::FAILURE;
        }
        if (! $target->is_active || ! $target->program->is_active) {
            $this->error('Kegiatan tujuan (atau programnya) ada di Data Terhapus. Kembalikan dulu dari halaman Input Data.');
            return self::FAILURE;
        }
        $pdId = (int) $target->program->perangkat_daerah_id;

        $ids  = array_values(array_unique(array_map('intval', $this->argument('sub_kegiatan'))));
        $subs = SubKegiatan::with('kegiatan.program.strategi')->whereIn('id', $ids)->get()->keyBy('id');
        if ($hilang = array_diff($ids, $subs->keys()->all())) {
            $this->error('Sub kegiatan tidak ditemukan: #' . implode(', #', $hilang));
            return self::FAILURE;
        }

        $this->line('');
        $this->line($eksekusi
            ? '<fg=red;options=bold>MODE: EKSEKUSI</> (perubahan disimpan)'
            : '<fg=yellow;options=bold>MODE: SIMULASI</> (tidak ada yang disimpan; tambah --eksekusi untuk menjalankan)');
        $this->line('Tujuan : kegiatan #' . $target->id . ' "' . $target->nama_kegiatan . '"');
        $this->line('         program #' . $target->program->id . ' "' . $target->program->nama_program . '"');
        $this->line('         ' . optional($target->program->perangkatDaerah)->nama . ' · strategi: ' . optional($target->program->strategi)->nama);
        $this->line('Bentrok: ' . match ($mode) {
            'tolak'  => 'tolak (bawaan)',
            'asal'   => 'pakai laporan sub kegiatan ASAL (yang dipindah)',
            'tujuan' => 'pakai laporan sub kegiatan di kegiatan TUJUAN',
        });
        $this->line('');

        // ── Rencana ─────────────────────────────────────────────────────────
        // Sub kegiatan di Data Terhapus tidak dianggap kembaran (tak boleh digabungi).
        $kembar = SubKegiatan::where('kegiatan_id', $target->id)->where('is_active', true)->get();
        $plan  = [];
        $salah = [];
        $nama  = [];
        foreach ($ids as $id) {
            $sk  = $subs[$id];
            $src = $sk->kegiatan?->program;
            if (! $sk->is_active) {
                $salah[] = "#{$id} ada di Data Terhapus. Kembalikan dulu dari halaman Input Data.";
                continue;
            }
            if ((int) $sk->kegiatan_id === (int) $target->id) {
                $salah[] = "#{$id} sudah berada di kegiatan tujuan.";
                continue;
            }
            if (! $src || (int) $src->perangkat_daerah_id !== $pdId) {
                $salah[] = "#{$id} milik perangkat daerah lain; tidak dipindah lintas perangkat daerah.";
                continue;
            }
            $kunci = $this->normal($sk->nama_sub_kegiatan);
            if (isset($nama[$kunci])) {
                $salah[] = "#{$id} bernama sama dengan #{$nama[$kunci]} yang juga dipindah. Pindahkan satu per satu.";
                continue;
            }
            $nama[$kunci] = $id;

            $lap  = LaporanOppkpke::where('sub_kegiatan_id', $id)->orderBy('tahun')->get();
            $twin = $kembar->first(fn ($t) => $this->normal($t->nama_sub_kegiatan) === $kunci);

            $bentrok = [];
            if ($twin) {
                $lapTwin = LaporanOppkpke::where('sub_kegiatan_id', $twin->id)->get()->keyBy(fn ($l) => (int) $l->tahun);
                foreach ($lap as $l) {
                    $t = $lapTwin->get((int) $l->tahun);
                    if (! $t) {
                        continue;
                    }
                    if ($mode === 'tolak') {
                        $salah[] = "#{$id} dan #{$twin->id} (nama sama di kegiatan tujuan) sama-sama punya laporan TA {$l->tahun}: "
                            . "#{$l->id} alokasi " . $this->rp($l->alokasi_anggaran) . " vs #{$t->id} alokasi " . $this->rp($t->alokasi_anggaran) . '. '
                            . "Pilih --bentrok=asal (pakai #{$l->id}) atau --bentrok=tujuan (pakai #{$t->id}).";
                        continue 2;
                    }
                    [$pakai, $buang] = $mode === 'asal' ? [$l, $t] : [$t, $l];
                    $isi = [];
                    foreach (self::KOLOM_ISI as $k) {
                        if ($this->kosong($pakai->getAttribute($k)) && ! $this->kosong($buang->getAttribute($k))) {
                            $isi[$k] = $buang->getAttribute($k);
                        }
                    }
                    $bentrok[] = ['tahun' => (int) $l->tahun, 'pakai' => $pakai, 'buang' => $buang, 'isi' => $isi];
                }
            }

            $plan[] = [
                'sk'      => $sk,
                'twin'    => $twin,
                'lap'     => $lap,
                'bentrok' => $bentrok,
                'sampah'  => LaporanOppkpke::onlyTrashed()->where('sub_kegiatan_id', $id)->count(),
                'srcKeg'  => $sk->kegiatan,
                'srcProg' => $src,
            ];
        }

        if ($salah) {
            foreach ($salah as $s) {
                $this->error($s);
            }
            $this->line('Tidak ada yang diubah.');
            return self::FAILURE;
        }

        $this->table(
            ['Sub kegiatan', 'Asal', 'Tindakan', 'Laporan aktif'],
            collect($plan)->map(fn ($p) => [
                '#' . $p['sk']->id . ' ' . $this->pendek($p['sk']->nama_sub_kegiatan),
                'keg #' . $p['srcKeg']->id . ' / prog #' . $p['srcProg']->id . ' ' . $this->pendek($p['srcProg']->nama_program, 30),
                $p['twin'] ? 'gabung ke #' . $p['twin']->id . ($p['bentrok'] ? ' (bentrok)' : '') : 'pindah',
                $p['lap']->isEmpty() ? '-' : $p['lap']->map(fn ($l) => $l->tahun . ': ' . $this->rp($l->alokasi_anggaran)
                    . ' / real ' . $this->rp($l->realisasi_total))->implode("\n"),
            ])->all()
        );

        foreach ($plan as $p) {
            foreach ($p['bentrok'] as $b) {
                $h = $this->hasil($b);
                $this->line("<options=bold>Bentrok TA {$b['tahun']}: #{$p['sk']->id} <-> #{$p['twin']->id}</>");
                $this->line("  DIPAKAI  laporan #{$b['pakai']->id}: alokasi " . $this->rp($b['pakai']->alokasi_anggaran)
                    . ', realisasi ' . $this->rp((float) $b['pakai']->realisasi_sem1 + (float) $b['pakai']->realisasi_sem2));
                $this->line("  DIBUANG  laporan #{$b['buang']->id}: alokasi " . $this->rp($b['buang']->alokasi_anggaran)
                    . ', realisasi ' . $this->rp((float) $b['buang']->realisasi_sem1 + (float) $b['buang']->realisasi_sem2)
                    . ' (soft delete, bisa dipulihkan)');
                if ($b['isi']) {
                    $this->line("  Kolom kosong diisi dari #{$b['buang']->id}: " . collect($b['isi'])->map(fn ($v, $k) => in_array($k, self::KOLOM_UANG, true)
                        ? "{$k} = " . $this->rp($v) : $k)->implode(', '));
                }
                $this->line('  <fg=green>HASIL</>    alokasi ' . $this->rp($h['alokasi']) . ', realisasi ' . $this->rp($h['realisasi']));
            }
        }

        foreach ($plan as $p) {
            if ((int) $p['srcProg']->strategi_id !== (int) $target->program->strategi_id) {
                $this->warn("#{$p['sk']->id}: strategi berubah dari \"" . optional($p['srcProg']->strategi)->nama
                    . '" ke "' . optional($target->program->strategi)->nama . '". Total per strategi ikut bergeser.');
            }
        }

        // Peringatan duplikat di luar kegiatan tujuan (tidak diubah, hanya diberi tahu).
        $lain = SubKegiatan::whereHas('kegiatan.program', fn ($q) => $q->where('perangkat_daerah_id', $pdId))
            ->whereNotIn('id', $ids)->where('kegiatan_id', '!=', $target->id)->get();
        foreach ($plan as $p) {
            $dup = $lain->filter(fn ($o) => $this->normal($o->nama_sub_kegiatan) === $this->normal($p['sk']->nama_sub_kegiatan));
            foreach ($dup as $d) {
                $this->warn("Perhatian: #{$d->id} di kegiatan #{$d->kegiatan_id} bernama sama dengan #{$p['sk']->id}. Cek kemungkinan duplikat.");
            }
        }

        // ── Total yang diharapkan sesudahnya ────────────────────────────────
        // Tanpa bentrok: sama persis. Dengan bentrok: berkurang tepat sebesar
        // laporan yang dibuang, ditambah kolom yang diisi ke laporan yang dipakai.
        $sebelum = $this->ringkas($pdId);
        $harap   = $sebelum;
        foreach ($plan as $p) {
            foreach ($p['bentrok'] as $b) {
                $y = $b['tahun'];
                $h = $this->hasil($b);
                $harap[$y]['n']--;
                $harap[$y]['alokasi'] = round($harap[$y]['alokasi'] - (float) $b['pakai']->alokasi_anggaran
                    - (float) $b['buang']->alokasi_anggaran + $h['alokasi'], 2);
                $harap[$y]['realisasi'] = round($harap[$y]['realisasi'] - (float) $b['pakai']->realisasi_total
                    - (float) $b['buang']->realisasi_total + $h['realisasi'], 2);
            }
        }

        // ── Jalankan (simulasi = jalankan lalu rollback) ───────────────────
        $tahunTerdampak = collect($plan)->flatMap(fn ($p) => $p['lap']->pluck('tahun'))->map(fn ($t) => (int) $t)->unique()->values();
        $dihapus = [];

        DB::beginTransaction();
        try {
            foreach ($plan as $p) {
                $sk    = $p['sk'];
                $props = [
                    'pelaksana'           => self::PELAKSANA,
                    'perangkat_daerah_id' => $pdId,
                    'dari_kegiatan_id'    => $p['srcKeg']->id,
                    'dari_program_id'     => $p['srcProg']->id,
                    'ke_kegiatan_id'      => $target->id,
                ];

                if (! $p['twin']) {
                    $sk->update(['kegiatan_id' => $target->id]);
                    AuditLog::record('sub_kegiatan.moved', "Pindah sub kegiatan \"{$sk->nama_sub_kegiatan}\" ke kegiatan \"{$target->nama_kegiatan}\"", $sk,
                        $props + ['laporan_ikut' => $p['lap']->count()]);
                    continue;
                }

                $twin = $p['twin'];
                foreach ($p['bentrok'] as $b) {
                    $pakai = $b['pakai'];
                    $pakai->fill($b['isi']);
                    $pakai->realisasi_total = (float) $pakai->realisasi_sem1 + (float) $pakai->realisasi_sem2;
                    $pakai->save();
                    $b['buang']->delete();   // soft delete
                }
                // Laporan aktif asal -> kembaran (termasuk yang "dipakai" bila mode asal).
                LaporanOppkpke::where('sub_kegiatan_id', $sk->id)->update(['sub_kegiatan_id' => $twin->id]);
                // Laporan terhapus (soft delete) asal ikut pindah sebagai riwayat, kecuali
                // bertabrakan dengan unique (sub_kegiatan_id, tahun, deleted_at) di kembaran.
                $riwayatPindah = 0;
                $riwayatBuang  = 0;
                foreach (LaporanOppkpke::onlyTrashed()->where('sub_kegiatan_id', $sk->id)->get() as $tr) {
                    $tabrak = LaporanOppkpke::withTrashed()->where('sub_kegiatan_id', $twin->id)
                        ->where('tahun', $tr->tahun)->where('deleted_at', $tr->deleted_at)->exists();
                    if ($tabrak) {
                        $riwayatBuang++;
                        continue;
                    }
                    LaporanOppkpke::withTrashed()->whereKey($tr->id)->update(['sub_kegiatan_id' => $twin->id]);
                    $riwayatPindah++;
                }
                $sk->delete();

                AuditLog::record('sub_kegiatan.merged', "Gabung sub kegiatan #{$sk->id} ke #{$twin->id} \"{$sk->nama_sub_kegiatan}\"", $twin, $props + [
                    'sub_kegiatan_asal_id' => $sk->id,
                    'laporan_dipindah'     => $p['lap']->count(),
                    'riwayat_dipindah'     => $riwayatPindah,
                    'riwayat_dibuang'      => $riwayatBuang,
                    'bentrok'              => collect($p['bentrok'])->map(fn ($b) => [
                        'tahun'           => $b['tahun'],
                        'mode'            => $mode,
                        'dipakai_id'      => $b['pakai']->id,
                        'dibuang_id'      => $b['buang']->id,
                        'alokasi_dipakai' => (float) $b['pakai']->alokasi_anggaran,
                        'alokasi_dibuang' => (float) $b['buang']->alokasi_anggaran,
                        'kolom_diisi'     => array_keys($b['isi']),
                    ])->all(),
                ]);
            }

            if ($this->option('hapus-asal-kosong')) {
                foreach (collect($plan)->pluck('srcKeg')->unique('id') as $k) {
                    $sisa = SubKegiatan::where('kegiatan_id', $k->id)->count();
                    if ($sisa > 0) {
                        $this->line("Kegiatan #{$k->id} tidak dihapus: masih berisi {$sisa} sub kegiatan lain.");
                        continue;
                    }
                    $k->delete();
                    $dihapus[] = "kegiatan #{$k->id} \"{$k->nama_kegiatan}\"";
                    AuditLog::record('kegiatan.deleted', "Hapus kegiatan kosong \"{$k->nama_kegiatan}\"", null,
                        ['pelaksana' => self::PELAKSANA, 'kegiatan_id' => $k->id, 'program_id' => $k->program_id, 'perangkat_daerah_id' => $pdId]);
                }
                foreach (collect($plan)->pluck('srcProg')->unique('id') as $pr) {
                    $sisa = Kegiatan::where('program_id', $pr->id)->count();
                    if ($sisa > 0) {
                        $this->line("Program #{$pr->id} tidak dihapus: masih berisi {$sisa} kegiatan.");
                        continue;
                    }
                    $pr->delete();
                    $dihapus[] = "program #{$pr->id} \"{$pr->nama_program}\"";
                    AuditLog::record('program.deleted', "Hapus program kosong \"{$pr->nama_program}\"", null,
                        ['pelaksana' => self::PELAKSANA, 'program_id' => $pr->id, 'kode_program' => $pr->kode_program, 'perangkat_daerah_id' => $pdId]);
                }
            }

            // Pagar: total laporan aktif perangkat daerah ini harus persis sesuai rencana.
            $sesudah = $this->ringkas($pdId);
            if (! $this->samaRingkas($harap, $sesudah)) {
                throw new \RuntimeException("Total laporan tidak sesuai rencana.\nDiharapkan: " . json_encode($harap) . "\nTernyata : " . json_encode($sesudah));
            }
            // Yang digabung sudah terhapus; sisanya wajib sudah di kegiatan tujuan.
            if (SubKegiatan::whereIn('id', $ids)->where('kegiatan_id', '!=', $target->id)->exists()) {
                throw new \RuntimeException('Ada sub kegiatan yang tidak berpindah.');
            }

            $eksekusi ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('DIBATALKAN, tidak ada yang berubah: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('');
        foreach ($dihapus as $d) {
            $this->line(($eksekusi ? 'Dihapus: ' : 'Akan dihapus: ') . $d);
        }
        $adaBentrok = collect($plan)->contains(fn ($p) => $p['bentrok']);
        $this->info($adaBentrok
            ? 'Cek total laporan aktif perangkat daerah ini per tahun: SESUAI rencana (sebelum -> sesudah).'
            : 'Cek total laporan aktif perangkat daerah ini per tahun: SAMA sebelum & sesudah.');
        foreach ($sesudah as $tahun => $r) {
            $s = $sebelum[$tahun] ?? ['n' => 0, 'alokasi' => 0, 'realisasi' => 0];
            $this->line("  TA {$tahun}: " . ($s == $r
                ? "{$r['n']} laporan · alokasi " . $this->rp($r['alokasi']) . ' · realisasi ' . $this->rp($r['realisasi'])
                : "{$s['n']} -> {$r['n']} laporan · alokasi " . $this->rp($s['alokasi']) . ' -> ' . $this->rp($r['alokasi'])
                    . ' · realisasi ' . $this->rp($s['realisasi']) . ' -> ' . $this->rp($r['realisasi'])));
        }

        if ($eksekusi) {
            foreach ($tahunTerdampak as $t) {
                Cache::forget("insight:snapshot:{$t}");
            }
            $this->info('Selesai. Perubahan disimpan dan tercatat di Audit Log.');
        } else {
            $this->line('');
            $this->info('Simulasi berhasil. Jalankan ulang dengan --eksekusi untuk menyimpan.');
        }

        return self::SUCCESS;
    }

    /** Alokasi & realisasi laporan yang dipakai setelah kolom kosongnya diisi. */
    private function hasil(array $b): array
    {
        $ambil = fn (string $k) => (float) ($b['isi'][$k] ?? $b['pakai']->getAttribute($k));

        return [
            'alokasi'   => round($ambil('alokasi_anggaran'), 2),
            'realisasi' => round($ambil('realisasi_sem1') + $ambil('realisasi_sem2'), 2),
        ];
    }

    /** Jumlah & total laporan aktif per tahun untuk satu perangkat daerah. */
    private function ringkas(int $pdId): array
    {
        return LaporanOppkpke::query()
            ->join('sub_kegiatan as s', 's.id', '=', 'laporan_oppkpke.sub_kegiatan_id')
            ->join('kegiatan as k', 'k.id', '=', 's.kegiatan_id')
            ->join('programs as p', 'p.id', '=', 'k.program_id')
            ->where('p.perangkat_daerah_id', $pdId)
            ->groupBy('laporan_oppkpke.tahun')
            ->orderBy('laporan_oppkpke.tahun')
            ->selectRaw('laporan_oppkpke.tahun as tahun, COUNT(*) as n, SUM(alokasi_anggaran) as alokasi, SUM(realisasi_total) as realisasi')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->tahun => ['n' => (int) $r->n, 'alokasi' => round((float) $r->alokasi, 2), 'realisasi' => round((float) $r->realisasi, 2)]])
            ->all();
    }

    private function samaRingkas(array $a, array $b): bool
    {
        $a = array_filter($a, fn ($r) => $r['n'] > 0);
        if (array_keys($a) != array_keys($b)) {
            return false;
        }
        foreach ($a as $t => $r) {
            if ($r['n'] !== $b[$t]['n']
                || abs($r['alokasi'] - $b[$t]['alokasi']) > 0.005
                || abs($r['realisasi'] - $b[$t]['realisasi']) > 0.005) {
                return false;
            }
        }
        return true;
    }

    private function kosong($v): bool
    {
        return $v === null || trim((string) $v) === '' || (is_numeric($v) && (float) $v == 0.0);
    }

    private function normal(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
    }

    private function pendek(string $s, int $n = 45): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }

    private function rp($v): string
    {
        return 'Rp ' . number_format((float) $v, 0, ',', '.');
    }
}
