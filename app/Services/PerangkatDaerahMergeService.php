<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Kegiatan;
use App\Models\PerangkatDaerah;
use App\Models\Pic;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penggabungan (merge) Perangkat Daerah duplikat.
 *
 * Duplikat muncul saat import RAT membuat PD baru karena nama di file cuma beda
 * tanda baca/spasi dengan PD yang sudah ada — beserta program & (kadang) akun
 * operatornya. Service ini menyatukan PD sumber ke PD tujuan dengan urutan yang
 * AMAN terhadap foreign key:
 *
 *   programs.perangkat_daerah_id     → ON DELETE CASCADE  (WAJIB dipindah dulu,
 *                                       kalau PD dihapus duluan, program + kegiatan
 *                                       + sub_kegiatan + laporan ikut TERHAPUS)
 *   pics.perangkat_daerah_id         → ON DELETE CASCADE  (pindah dulu juga)
 *   users.perangkat_daerah_id        → ON DELETE SET NULL
 *   conversations.perangkat_daerah_id→ ON DELETE SET NULL
 *
 * Semua dalam satu transaksi: kalau ada yang gagal, semuanya batal (tidak ada
 * PD yang setengah ter-merge).
 */
class PerangkatDaerahMergeService
{
    /**
     * Normalisasi nama untuk deteksi duplikat: buang semua non-alfanumerik
     * (koma, titik, newline) → spasi, lalu lowercase & rapatkan. Sama dengan
     * normalisasi di import supaya konsisten.
     */
    public static function normalizeName(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
    }

    /**
     * Kelompokkan PD yang namanya identik setelah normalisasi.
     * Return: Collection of groups (setiap group = Collection PerangkatDaerah),
     * hanya group yang berisi >1 anggota (benar-benar duplikat).
     */
    public function duplicateGroups(Collection $all): Collection
    {
        return $all
            ->groupBy(fn ($pd) => self::normalizeName((string) $pd->nama))
            ->filter(fn ($group) => $group->count() > 1)
            ->values();
    }

    /**
     * Gabungkan $source ke dalam $target. Mengembalikan ringkasan aksi.
     *
     * @throws \InvalidArgumentException bila source === target
     */
    public function merge(PerangkatDaerah $source, PerangkatDaerah $target): array
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Perangkat daerah sumber dan tujuan tidak boleh sama.');
        }

        return DB::transaction(function () use ($source, $target) {
            $movedPrograms  = 0;
            $mergedPrograms = 0;

            // 1) PROGRAMS — pindah ke target. Bila target sudah punya program dengan
            //    (strategi_id, kode_program) yang sama (akan bentrok unique key),
            //    pindahkan kegiatan program sumber ke program target lalu hapus
            //    program sumber — jadi datanya menyatu, bukan menabrak.
            foreach (Program::where('perangkat_daerah_id', $source->id)->get() as $prog) {
                $conflict = Program::where('perangkat_daerah_id', $target->id)
                    ->where('strategi_id', $prog->strategi_id)
                    ->where('kode_program', $prog->kode_program)
                    ->first();

                if ($conflict) {
                    Kegiatan::where('program_id', $prog->id)->update(['program_id' => $conflict->id]);
                    $prog->delete();
                    $mergedPrograms++;
                } else {
                    $prog->update(['perangkat_daerah_id' => $target->id]);
                    $movedPrograms++;
                }
            }

            // 2) PICS (CASCADE) — pindah agar tidak ikut terhapus saat PD sumber dihapus.
            $movedPics = Pic::where('perangkat_daerah_id', $source->id)
                ->update(['perangkat_daerah_id' => $target->id]);

            // 3) CONVERSATIONS (SET NULL) — pindah agar tiket chat tetap tertaut ke PD.
            $movedConversations = Conversation::where('perangkat_daerah_id', $source->id)
                ->update(['perangkat_daerah_id' => $target->id]);

            // 4) OPERATOR akun dari PD sumber → dipindah ke target & DINONAKTIFKAN.
            //    Tidak dihapus keras: akun bisa saja punya jejak laporan (created_by,
            //    FK RESTRICT) sehingga delete akan gagal. Dinonaktifkan = duplikat
            //    aktif hilang, data aman, dan Tim IT/Admin bisa hapus permanen lewat
            //    Kelola Pengguna bila memang perlu.
            $deactivatedOperators = [];
            foreach (User::where('perangkat_daerah_id', $source->id)->get() as $op) {
                $op->update(['perangkat_daerah_id' => $target->id, 'is_active' => false]);
                $deactivatedOperators[] = $op->email;
            }

            // 5) Hapus PD sumber — sekarang sudah tidak punya program/pic, jadi
            //    CASCADE tidak menyentuh data apa pun.
            $sourceName = $source->nama;
            $sourceId   = $source->id;
            $source->delete();

            AuditLog::record(
                'perangkat_daerah.merged',
                "Gabung PD \"{$sourceName}\" (#{$sourceId}) ke \"{$target->nama}\" (#{$target->id})",
                $target,
                [
                    'source_id'             => $sourceId,
                    'source_name'           => $sourceName,
                    'target_id'             => $target->id,
                    'programs_moved'        => $movedPrograms,
                    'programs_merged'       => $mergedPrograms,
                    'pics_moved'            => $movedPics,
                    'conversations_moved'   => $movedConversations,
                    'operators_deactivated' => $deactivatedOperators,
                ]
            );

            return [
                'source_name'           => $sourceName,
                'target_name'           => $target->nama,
                'programs_moved'        => $movedPrograms,
                'programs_merged'       => $mergedPrograms,
                'pics_moved'            => $movedPics,
                'conversations_moved'   => $movedConversations,
                'operators_deactivated' => $deactivatedOperators,
            ];
        });
    }
}
