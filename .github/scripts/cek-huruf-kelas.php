<?php
/**
 * Cari rujukan kelas App\..., Tests\..., Database\... yang huruf besar/kecilnya
 * berbeda dari nama kelas sebenarnya, misalnya App\Models\jalurpendaftaran
 * untuk JalurPendaftaran.
 *
 * Di Windows (XAMPP) rujukan seperti itu tetap jalan karena sistem berkasnya
 * tidak membedakan huruf. Di Linux (Docker, vps1) autoloader mencari berkas
 * jalurpendaftaran.php yang tidak ada, lalu gagal "Class not found".
 *
 * Butuh vendor/composer/autoload_classmap.php hasil autoload teroptimasi
 * (composer install di proyek ini sudah menghasilkannya).
 *
 * Pemakaian: php .github/scripts/cek-huruf-kelas.php app routes config database tests resources/views
 */
$classmap = require __DIR__ . '/../../vendor/composer/autoload_classmap.php';

$menurutHurufKecil = [];
foreach (array_keys($classmap) as $kelas) {
    $menurutHurufKecil[strtolower($kelas)] = $kelas;
}

$temuan = 0;
foreach (array_slice($argv, 1) as $folder) {
    if (! is_dir($folder)) {
        continue;
    }
    $berkas = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($berkas as $info) {
        if (! str_ends_with($info->getFilename(), '.php')) {
            continue;
        }
        foreach (file($info->getPathname()) as $i => $baris) {
            // Baris komentar dilewati: komentar boleh menyebut nama yang salah
            // (misalnya menjelaskan bug ini), dan tidak pernah di-autoload.
            if (preg_match('~^\s*(//|#|\*|/\*)~', $baris)) {
                continue;
            }
            if (! preg_match_all('/\\\\?\b(?:App|Tests|Database)(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', $baris, $m)) {
                continue;
            }
            foreach ($m[0] as $rujukan) {
                $rujukan = ltrim($rujukan, '\\');
                if (isset($classmap[$rujukan])) {
                    continue;
                }
                $benar = $menurutHurufKecil[strtolower($rujukan)] ?? null;
                if ($benar !== null) {
                    printf("%s:%d  %s  ->  %s\n", $info->getPathname(), $i + 1, $rujukan, $benar);
                    $temuan++;
                }
            }
        }
    }
}

if ($temuan > 0) {
    fwrite(STDERR, "\n{$temuan} rujukan kelas salah huruf. Jalan di Windows, gagal di Linux.\n");
    exit(1);
}
echo "Semua rujukan kelas cocok huruf besar/kecilnya.\n";
