<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validasi FORMAT NIK (KTP) Indonesia — bukan verifikasi keberadaan.
 *
 * Struktur NIK 16 digit: PP KK DD | TTBBTT | SSSS
 *   1-2   Kode provinsi          (harus salah satu kode provinsi valid)
 *   3-4   Kode kabupaten/kota
 *   5-6   Kode kecamatan
 *   7-8   Tanggal lahir          (untuk perempuan ditambah 40, jadi 41-71)
 *   9-10  Bulan lahir            (01-12)
 *   11-12 Tahun lahir (2 digit)
 *   13-16 Nomor urut             (0001-9999, tidak boleh 0000)
 *
 * Ini menolak NIK ngawur yang sederhana (kode provinsi salah, tanggal/bulan
 * mustahil, semua digit sama). Tidak menjamin NIK benar-benar terdaftar di
 * Dukcapil — untuk itu perlu API resmi berbayar/berizin.
 */
class ValidNik implements ValidationRule
{
    /** Kode provinsi resmi (2 digit pertama NIK). */
    private const KODE_PROVINSI = [
        '11','12','13','14','15','16','17','18','19', // Sumatera
        '21',                                          // Kepri
        '31','32','33','34','35','36',                 // Jawa
        '51','52','53',                                // Bali & Nusa Tenggara
        '61','62','63','64','65',                      // Kalimantan
        '71','72','73','74','75','76',                 // Sulawesi
        '81','82',                                     // Maluku
        '91','92','93','94','95','96',                 // Papua (termasuk pemekaran)
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nik = (string) $value;

        if (!preg_match('/^\d{16}$/', $nik)) {
            $fail('Nomor KTP (NIK) harus tepat 16 digit angka.');
            return;
        }

        // Tolak semua digit sama (mis. 1111111111111111) — jelas ngawur.
        if (preg_match('/^(\d)\1{15}$/', $nik)) {
            $fail('Nomor KTP (NIK) tidak valid (pola tidak wajar).');
            return;
        }

        $prov = substr($nik, 0, 2);
        if (!in_array($prov, self::KODE_PROVINSI, true)) {
            $fail('Nomor KTP (NIK) tidak valid: kode provinsi tidak dikenal.');
            return;
        }

        // Tanggal lahir: kurangi 40 jika perempuan.
        $tgl = (int) substr($nik, 6, 2);
        if ($tgl > 40) {
            $tgl -= 40;
        }
        if ($tgl < 1 || $tgl > 31) {
            $fail('Nomor KTP (NIK) tidak valid: tanggal lahir tidak masuk akal.');
            return;
        }

        $bln = (int) substr($nik, 8, 2);
        if ($bln < 1 || $bln > 12) {
            $fail('Nomor KTP (NIK) tidak valid: bulan lahir tidak masuk akal.');
            return;
        }

        // Nomor urut 4 digit terakhir tidak boleh 0000.
        if (substr($nik, 12, 4) === '0000') {
            $fail('Nomor KTP (NIK) tidak valid: nomor urut tidak boleh 0000.');
            return;
        }
    }
}
