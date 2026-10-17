<?php
/*
 * =============================================================================
 * tools/rahasia.php — kelola temuan kredensial (gitleaks) dari deteksi sampai
 * selesai. Jalan di laptop (PowerShell/Git Bash, PHP XAMPP) dan di CI.
 *
 *   php tools/rahasia.php pindai [--dasar <ref>] [--ci]
 *       Temuan dikelompokkan PER RAHASIA (bukan per baris): aturan, lokasinya
 *       di kode sekarang, sejak commit mana, dan apakah baru di push/PR ini.
 *       Nilai rahasia tidak pernah dicetak. Keluar 1 kalau ada temuan.
 *   php tools/rahasia.php perbaiki [--ya]
 *       Rahasia yang masih di kode PHP dipindah ke env():
 *         'password' => 'xxx'        ->  'password' => env('NAMA')
 *         env('NAMA', 'xxx')         ->  env('NAMA')
 *       NAMA ditambahkan ke .env.example (tanpa nilai). Nilai lamanya ditulis ke
 *       .env.rahasia-pindah (diabaikan Git) untuk diisi ke server SEBELUM deploy.
 *   php tools/rahasia.php tandai <no...> --sudah-dirotasi
 *   php tools/rahasia.php tandai <no...> --bukan-rahasia "alasan"
 *       Catat fingerprint ke .gitleaksignore. --sudah-dirotasi ditolak kalau
 *       rahasianya masih ada di kode.
 *   php tools/rahasia.php sidik <no...>
 *       Sidik bcrypt untuk mengecek di server apakah nilai yang bocor masih
 *       dipakai di .env produksi (= belum dirotasi). Dibawa dari repo SIA; di
 *       vps1 RPKD belum ada pencocoknya (rpkd.env diperiksa manual).
 *   php tools/rahasia.php hook
 *       Pasang pre-commit hook: commit yang menambah kredensial dibatalkan.
 *   php tools/rahasia.php staged        (dipanggil hook)
 *
 * gitleaks: dari PATH, env GITLEAKS, atau diunduh otomatis (versi terkunci,
 * checksum diverifikasi) ke tools/.bin/.
 * =============================================================================
 */
declare(strict_types=1);

const GITLEAKS_VERSI = '8.30.0';

function keluar(string $pesan, int $kode = 1): void { fwrite(STDERR, $pesan . "\n"); exit($kode); }
function info(string $s = ''): void { echo $s, "\n"; }

/** Jalankan perintah tanpa shell. @return array{0:int,1:string,2:string} */
function jalan(array $cmd, ?string $cwd = null): array
{
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipa, $cwd);
    if (!is_resource($p)) {
        keluar('Tidak bisa menjalankan: ' . $cmd[0]);
    }
    $out = stream_get_contents($pipa[1]);
    $err = stream_get_contents($pipa[2]);
    fclose($pipa[1]);
    fclose($pipa[2]);
    return [proc_close($p), (string) $out, (string) $err];
}

function git(array $args): string
{
    [$rc, $out, $err] = jalan(array_merge(['git'], $args));
    if ($rc !== 0) {
        keluar('git ' . implode(' ', $args) . " gagal: " . trim($err));
    }
    return $out;
}

// --- gitleaks ------------------------------------------------------------------
function cari_di_path(string $nama): ?string
{
    foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $d) {
        foreach (PHP_OS_FAMILY === 'Windows' ? [$nama . '.exe', $nama] : [$nama] as $n) {
            $f = rtrim($d, '/\\') . DIRECTORY_SEPARATOR . $n;
            if ($d !== '' && is_file($f) && is_executable($f)) {
                return $f;
            }
        }
    }
    return null;
}

function unduh(string $url): string
{
    $ctx = stream_context_create(['http' => ['user_agent' => 'rpkd-rahasia', 'timeout' => 60, 'follow_location' => 1]]);
    $isi = @file_get_contents($url, false, $ctx);
    if ($isi === false || $isi === '') {
        keluar("Gagal mengunduh $url. Pasang gitleaks manual (https://github.com/gitleaks/gitleaks/releases) atau set env GITLEAKS.");
    }
    return $isi;
}

function gitleaks(string $root): string
{
    if (($e = getenv('GITLEAKS')) && is_file($e)) {
        return $e;
    }
    if ($p = cari_di_path('gitleaks')) {
        return $p;
    }
    $win = PHP_OS_FAMILY === 'Windows';
    $dir = $root . '/tools/.bin';
    $bin = $dir . ($win ? '/gitleaks.exe' : '/gitleaks');
    $penanda = $dir . '/gitleaks.versi';
    if (is_file($bin) && @file_get_contents($penanda) === GITLEAKS_VERSI) {
        return $bin;
    }
    $os = ['Windows' => 'windows', 'Linux' => 'linux', 'Darwin' => 'darwin'][PHP_OS_FAMILY] ?? keluar('OS tidak didukung: ' . PHP_OS_FAMILY);
    $m = strtolower(php_uname('m'));
    $arch = in_array($m, ['x86_64', 'amd64'], true) ? 'x64' : (in_array($m, ['aarch64', 'arm64'], true) ? 'arm64' : keluar("Arsitektur tidak didukung: $m"));
    $v = GITLEAKS_VERSI;
    $aset = "gitleaks_{$v}_{$os}_{$arch}." . ($win ? 'zip' : 'tar.gz');
    $dasar = "https://github.com/gitleaks/gitleaks/releases/download/v$v";
    info("Mengunduh gitleaks $v ($aset)...");
    $arsip = unduh("$dasar/$aset");
    $sums = unduh("$dasar/gitleaks_{$v}_checksums.txt");
    if (!preg_match('/^([0-9a-f]{64})\s+' . preg_quote($aset, '/') . '$/m', $sums, $mm) || !hash_equals($mm[1], hash('sha256', $arsip))) {
        keluar("Checksum $aset tidak cocok; berkas tidak dipakai.");
    }
    @mkdir($dir, 0755, true);
    $tmp = $dir . '/' . $aset;
    file_put_contents($tmp, $arsip);
    if ($win) {
        $z = new ZipArchive();
        if ($z->open($tmp) !== true || !$z->extractTo($dir, ['gitleaks.exe'])) {
            keluar('Gagal membuka arsip zip gitleaks (ekstensi zip PHP aktif?).');
        }
        $z->close();
    } else {
        (new PharData($tmp))->extractTo($dir, 'gitleaks', true);
        chmod($bin, 0755);
    }
    unlink($tmp);
    file_put_contents($penanda, GITLEAKS_VERSI);
    return $bin;
}

// --- Analisis ----------------------------------------------------------------------
/**
 * Temuan gitleaks dikelompokkan per nilai rahasia. Nilai hanya ada di memori.
 * @return array<int,array<string,mixed>> (berurutan; nomor = indeks + 1)
 */
function analisis(string $root, ?string $dasar): array
{
    $bin = gitleaks($root);
    $tmp = tempnam(sys_get_temp_dir(), 'gl');
    @chmod($tmp, 0600);
    try {
        [$rc, , $err] = jalan([$bin, 'git', $root, '--report-format', 'json', '--report-path', $tmp,
            '--exit-code', '0', '--no-banner', '--log-level', 'error'], $root);
        if ($rc !== 0) {
            keluar("gitleaks gagal: " . trim($err));
        }
        $temuan = json_decode((string) file_get_contents($tmp), true) ?: [];
    } finally {
        @unlink($tmp);
    }
    $baru = [];
    if ($dasar !== null && $dasar !== '') {
        [$rc, $out] = jalan(['git', 'rev-list', "$dasar..HEAD"], $root);
        if ($rc === 0) {
            $baru = array_flip(array_filter(explode("\n", trim($out))));
        }
    }
    $kunci = random_bytes(32);
    $grup = [];
    foreach ($temuan as $t) {
        $id = hash_hmac('sha256', (string) $t['Secret'], $kunci);
        $g = &$grup[$id];
        $g['rahasia'] = (string) $t['Secret'];
        $g['aturan'][$t['RuleID']] = true;
        $g['file'][$t['File']] = true;
        $g['commit'][$t['Commit']] = $t['Date'];
        $g['fingerprint'][$t['Fingerprint']] = true;
        $g['baru'] = ($g['baru'] ?? false) || isset($baru[$t['Commit']]);
        $g['sejak'] = min($g['sejak'] ?? $t['Date'], $t['Date']);
        unset($g);
    }
    foreach ($grup as &$g) {
        $g['lokasi'] = [];
        $cari = strtok($g['rahasia'], "\n");
        if ($cari !== false && strlen($cari) >= 4) {
            [$rc, $out] = jalan(['git', 'grep', '-n', '-I', '-F', '-e', $cari, 'HEAD', '--'], $root);
            if ($rc === 0) {
                foreach (explode("\n", trim($out)) as $b) {
                    if (preg_match('/^HEAD:(.+?):(\d+):(.*)$/s', $b, $m)) {
                        $g['lokasi'][] = ['file' => $m[1], 'baris' => (int) $m[2], 'isi' => $m[3]];
                    }
                }
            }
        }
        $g['otomatis'] = $g['lokasi'] !== [] && array_reduce($g['lokasi'], fn ($ok, $l) => $ok && pola($l['isi'], $g['rahasia']) !== null, true);
    }
    unset($g);
    $grup = array_values($grup);
    usort($grup, fn ($a, $b) => [$a['sejak'], array_key_first($a['file'])] <=> [$b['sejak'], array_key_first($b['file'])]);
    return $grup;
}

/** Pola yang bisa dipindah otomatis ke env(). @return array{jenis:string,kunci:string}|null */
function pola(string $baris, string $rahasia): ?array
{
    $q = preg_quote($rahasia, '/');
    if (preg_match('/env\(\s*([\'"])([A-Za-z0-9_]+)\1\s*,\s*([\'"])' . $q . '\3\s*\)/', $baris, $m)) {
        return ['jenis' => 'env-default', 'kunci' => $m[2]];
    }
    if (preg_match('/([\'"])([A-Za-z0-9_.\-]+)\1\s*=>\s*([\'"])' . $q . '\3/', $baris, $m)) {
        return ['jenis' => 'array', 'kunci' => $m[2]];
    }
    return null;
}

function cetak_tabel(array $grup): void
{
    info(sprintf('%-3s %-24s %-38s %-34s %s', '#', 'Aturan', 'Lokasi (sekarang / dulu)', 'Riwayat', 'Status'));
    foreach ($grup as $i => $g) {
        $lok = $g['lokasi'] === []
            ? 'dulu: ' . implode(', ', array_keys($g['file']))
            : $g['lokasi'][0]['file'] . ':' . $g['lokasi'][0]['baris'] . (count($g['lokasi']) > 1 ? ' (+' . (count($g['lokasi']) - 1) . ')' : '');
        $c = array_key_first($g['commit']);
        $riw = count($g['commit']) . ' commit, sejak ' . substr($g['sejak'], 0, 10) . ' (' . substr($c, 0, 7) . ')';
        $st = ($g['lokasi'] !== [] ? 'MASIH DI KODE' . ($g['otomatis'] ? ' (bisa otomatis)' : ' (manual)') : 'hanya riwayat') . ($g['baru'] ? ', BARU' : '');
        info(sprintf('%-3s %-24s %-38s %-34s %s', $i + 1, implode(',', array_keys($g['aturan'])), mb_strimwidth($lok, 0, 60, '…'), $riw, $st));
    }
}

function ringkasan_ci(array $grup): void
{
    $f = getenv('GITHUB_STEP_SUMMARY');
    $md = "### Kredensial di riwayat Git: " . count($grup) . " rahasia\n\n";
    $md .= "Nilai tidak ditampilkan. Penanganan: `php tools/rahasia.php pindai` di laptop, lalu ikuti SECURITY.md.\n\n";
    $md .= "| # | Aturan | Lokasi di kode sekarang | Commit | Sejak | Status |\n|---|---|---|---|---|---|\n";
    foreach ($grup as $i => $g) {
        $lok = $g['lokasi'] === []
            ? 'dulu: ' . implode('<br>', array_map(fn ($f) => "`$f`", array_keys($g['file'])))
            : implode('<br>', array_map(fn ($l) => "`{$l['file']}:{$l['baris']}`", $g['lokasi']));
        $md .= sprintf("| %d | %s | %s | %d | %s | %s |\n", $i + 1, implode(', ', array_keys($g['aturan'])), $lok,
            count($g['commit']), substr($g['sejak'], 0, 10),
            ($g['lokasi'] !== [] ? '**masih di kode**' : 'hanya riwayat') . ($g['baru'] ? ', **baru**' : ''));
        foreach ($g['lokasi'] as $l) {
            echo "::error file={$l['file']},line={$l['baris']}::Kredensial #" . ($i + 1) . " (" . implode(',', array_keys($g['aturan'])) . ") masih di kode. Pindahkan ke .env: php tools/rahasia.php perbaiki\n";
        }
    }
    $md .= "\n<details><summary>Fingerprint (untuk .gitleaksignore, HANYA setelah dirotasi)</summary>\n\n```\n";
    foreach ($grup as $i => $g) {
        $md .= "# #" . ($i + 1) . "\n" . implode("\n", array_keys($g['fingerprint'])) . "\n";
    }
    $md .= "```\n</details>\n";
    if ($f) {
        file_put_contents($f, $md, FILE_APPEND);
    }
}

// --- Perintah --------------------------------------------------------------------
function pindai(string $root, array $opsi): int
{
    $grup = analisis($root, $opsi['dasar'] ?? null);
    if ($grup === []) {
        info('Bersih: tidak ada kredensial di riwayat Git (setelah .gitleaksignore).');
        return 0;
    }
    cetak_tabel($grup);
    if (!empty($opsi['ci'])) {
        ringkasan_ci($grup);
    }
    info('');
    info('Langkah (detail: SECURITY.md):');
    info('  1. Masih di kode   -> php tools/rahasia.php perbaiki   (pindah ke env(), nilai lama ke .env.rahasia-pindah)');
    info('  2. Server          -> isi nilainya di /srv/rpkd/secrets/rpkd.env   (sebelum kode baru dideploy)');
    info('  3. Rotasi          -> ganti nilainya di layanan asal dan di rpkd.env, lalu rpkd-deploy <tag aktif>');
    info('  4. Tandai          -> php tools/rahasia.php tandai <no> --sudah-dirotasi   (atau --bukan-rahasia "alasan")');
    return 1;
}

function nama_env(string $file, string $kunci): string
{
    $k = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $kunci));
    $dasar = strtoupper((string) preg_replace('/(?<!^)([A-Z])/', '_$1', pathinfo($file, PATHINFO_FILENAME)));
    $dasar = (string) preg_replace('/[^A-Z0-9]+/', '_', $dasar);
    return str_starts_with($k, $dasar . '_') ? $k : $dasar . '_' . $k;
}

function tanya(string $teks, string $bawaan, bool $ya): string
{
    if ($ya || !stream_isatty(STDIN)) {
        return $bawaan;
    }
    echo "$teks [$bawaan]: ";
    $j = trim((string) fgets(STDIN));
    return $j === '' ? $bawaan : $j;
}

function perbaiki(string $root, array $opsi): int
{
    $grup = analisis($root, null);
    $aktif = array_filter($grup, fn ($g) => $g['lokasi'] !== []);
    if ($aktif === []) {
        info('Tidak ada kredensial yang masih di kode. Yang tersisa hanya di riwayat: rotasi lalu tandai.');
        return 0;
    }
    [$rc] = jalan(['git', 'check-ignore', '-q', '.env.rahasia-pindah'], $root);
    if ($rc !== 0) {
        keluar('.env.rahasia-pindah TIDAK diabaikan Git (.gitignore harus memuat .env.*). Dibatalkan.');
    }
    $ubah = [];      // file => isi baru
    $envBaru = [];   // NAMA => nilai lama
    $manual = [];
    foreach ($grup as $i => $g) {
        foreach ($g['lokasi'] as $l) {
            $no = $i + 1;
            $p = pola($l['isi'], $g['rahasia']);
            if ($p === null || !str_ends_with($l['file'], '.php')) {
                $manual[] = "#$no {$l['file']}:{$l['baris']}";
                continue;
            }
            $nama = $p['jenis'] === 'env-default' ? $p['kunci'] : nama_env($l['file'], $p['kunci']);
            $nama = strtoupper(tanya("#$no {$l['file']}:{$l['baris']} ('{$p['kunci']}') -> nama variabel .env", $nama, !empty($opsi['ya'])));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $nama)) {
                keluar("Nama variabel tidak sah: $nama");
            }
            if (isset($envBaru[$nama]) && $envBaru[$nama] !== $g['rahasia']) {
                keluar("$nama dipakai untuk dua nilai berbeda; pilih nama lain.");
            }
            $isi = $ubah[$l['file']] ?? (string) file_get_contents("$root/{$l['file']}");
            $baris = preg_split('/(?<=\n)/', $isi);
            $idx = $l['baris'] - 1;
            $q = preg_quote($g['rahasia'], '/');
            $baris[$idx] = $p['jenis'] === 'env-default'
                ? (string) preg_replace('/env\(\s*([\'"])' . preg_quote($p['kunci'], '/') . '\1\s*,\s*([\'"])' . $q . '\2\s*\)/', "env('$nama')", $baris[$idx], 1)
                : (string) preg_replace('/(([\'"])' . preg_quote($p['kunci'], '/') . '\2\s*=>\s*)([\'"])' . $q . '\3/', "\$1env('$nama')", $baris[$idx], 1);
            $ubah[$l['file']] = implode('', $baris);
            $envBaru[$nama] = $g['rahasia'];
        }
    }
    foreach ($ubah as $file => $isi) {
        file_put_contents("$root/$file", $isi);
        [$rc, $out] = jalan([PHP_BINARY, '-l', "$root/$file"]);
        if ($rc !== 0) {
            keluar("Sintaks $file rusak setelah diubah; kembalikan dengan: git checkout -- $file\n$out");
        }
    }
    if ($envBaru !== []) {
        $contoh = "$root/.env.example";
        $isi = is_file($contoh) ? (string) file_get_contents($contoh) : '';
        $tambah = '';
        foreach (array_keys($envBaru) as $n) {
            if (!preg_match('/^' . $n . '=/m', $isi)) {
                $tambah .= "$n=\n";
            }
        }
        if ($tambah !== '') {
            file_put_contents($contoh, rtrim($isi, "\n") . "\n\n# Dipindah dari kode oleh tools/rahasia.php (nilai hanya di .env server)\n" . $tambah);
        }
        $f = "$root/.env.rahasia-pindah";
        $teks = "# Nilai LAMA untuk .env server (/srv/rpkd/secrets/rpkd.env). Rotasi setelahnya. Hapus file ini setelah dipakai.\n";
        foreach ($envBaru as $n => $v) {
            $teks .= $n . '=' . (str_contains($v, "'") ? '"' . addcslashes($v, "\"\\") . '"' : "'$v'") . "\n";
        }
        file_put_contents($f, $teks);
        @chmod($f, 0600);
    }
    info('Diubah: ' . (count($ubah) ? implode(', ', array_keys($ubah)) : '-'));
    info('Variabel baru: ' . (count($envBaru) ? implode(', ', array_keys($envBaru)) : '-') . ' (ditambahkan ke .env.example, tanpa nilai)');
    if ($manual) {
        info('Perlu ditangani manual (bukan pola config PHP sederhana): ' . implode('; ', $manual));
    }
    if ($envBaru) {
        info('');
        info('Berikutnya:');
        info('  1. Periksa: git diff ; lalu commit + push (CI masih merah sampai langkah 4: wajar).');
        info('  2. Server SEBELUM kode ini dideploy (kalau tidak, nilai kosong):');
        info('       salin baris dari .env.rahasia-pindah ke /srv/rpkd/secrets/rpkd.env di vps1 (sudo nano),');
        info('       lalu hapus .env.rahasia-pindah di laptop.');
        info('  3. Rotasi di layanan asal, ganti nilainya di rpkd.env: ' . implode(' ', array_keys($envBaru)) . ' ; lalu rpkd-deploy <tag aktif>');
        info('  4. php tools/rahasia.php tandai <no> --sudah-dirotasi ; commit .gitleaksignore');
    }
    return 0;
}

function tandai(string $root, array $no, array $opsi): int
{
    $rotasi = !empty($opsi['sudah-dirotasi']);
    $alasan = trim((string) ($opsi['bukan-rahasia'] ?? ''));
    if ($rotasi === ($alasan !== '')) {
        keluar('Pilih salah satu: --sudah-dirotasi ATAU --bukan-rahasia "alasan".');
    }
    $grup = analisis($root, null);
    $f = "$root/.gitleaksignore";
    $isi = is_file($f) ? (string) file_get_contents($f) : '';
    $ada = array_flip(array_map('trim', explode("\n", $isi)));
    [, $nama] = jalan(['git', 'config', 'user.name'], $root);
    $nama = trim($nama) ?: 'tidak dikenal';
    $tambah = '';
    foreach ($no as $n) {
        $g = $grup[(int) $n - 1] ?? keluar("Nomor $n tidak ada. Lihat: php tools/rahasia.php pindai");
        if ($rotasi && $g['lokasi'] !== []) {
            keluar("#$n masih ada di kode (" . $g['lokasi'][0]['file'] . "). Pindahkan dulu: php tools/rahasia.php perbaiki");
        }
        $fp = array_filter(array_keys($g['fingerprint']), fn ($x) => !isset($ada[$x]));
        $tambah .= "\n# " . date('Y-m-d') . ' ' . ($rotasi ? 'DIROTASI' : "BUKAN RAHASIA: $alasan") . ' (' . implode(',', array_keys($g['aturan'])) . ', ' . array_key_first($g['file']) . ", oleh $nama)\n" . implode("\n", $fp) . "\n";
    }
    file_put_contents($f, rtrim($isi, "\n") . "\n" . $tambah);
    info('.gitleaksignore diperbarui. Cek: php tools/rahasia.php pindai ; lalu commit .gitleaksignore');
    return 0;
}

/**
 * Sidik bcrypt per temuan, untuk mengecek di server apakah nilai di .env produksi
 * masih sama dengan yang bocor (= belum dirotasi) tanpa nilai mana pun berpindah:
 *   laptop: php tools/rahasia.php sidik 1 2
 *   server: cocokkan dengan password_verify() atas setiap nilai .env produksi
 * bcrypt(cost 12, garam acak) atas base64(sha256(nilai)): lambat ditebak, tidak bisa dibalik.
 */
function sidik_dari(string $nilai): string
{
    return password_hash(base64_encode(hash('sha256', $nilai, true)), PASSWORD_BCRYPT, ['cost' => 12]);
}

function sidik(string $root, array $no): int
{
    $grup = analisis($root, null);
    $semua = [];
    foreach ($no as $n) {
        $g = $grup[(int) $n - 1] ?? keluar("Nomor $n tidak ada. Lihat: php tools/rahasia.php pindai");
        $h = sidik_dari($g['rahasia']);
        $semua[] = "'$h'";
        info('sidik ' . count($semua) . " = #$n " . implode(',', array_keys($g['aturan'])) . ' (dulu di ' . implode(', ', array_keys($g['file'])) . ')');
    }
    info('');
    info('Sidik: ' . implode(' ', $semua));
    info('Cocokkan di server dengan password_verify(base64_encode(hash("sha256", <nilai>, true)), <sidik>) untuk tiap nilai rpkd.env.');
    return 0;
}

function staged(string $root): int
{
    [$rc, $out, $err] = jalan([gitleaks($root), 'git', $root, '--pre-commit', '--staged', '--redact', '--no-banner', '-v'], $root);
    if ($rc !== 0) {
        fwrite(STDERR, $out . $err);
        fwrite(STDERR, "\nCOMMIT DIBATALKAN: perubahan ini memuat kemungkinan kredensial (nilai disamarkan di atas).\n"
            . "Pindahkan nilainya ke .env dan pakai env('NAMA'). Kalau memang bukan rahasia, beri komentar\n"
            . "gitleaks:allow di baris itu. Melewati hook (git commit --no-verify) tetap akan ditolak CI.\n");
    }
    return $rc;
}

function hook(string $root): int
{
    $dir = trim(git(['rev-parse', '--git-path', 'hooks']));
    if (!str_starts_with($dir, '/') && !preg_match('/^[A-Za-z]:/', $dir)) {
        $dir = "$root/$dir";
    }
    @mkdir($dir, 0755, true);
    $f = "$dir/pre-commit";
    if (is_file($f) && !str_contains((string) file_get_contents($f), 'tools/rahasia.php')) {
        rename($f, "$f.lama");
        info("Hook lama disimpan sebagai $f.lama");
    }
    file_put_contents($f, "#!/bin/sh\n# Dipasang oleh tools/rahasia.php hook: tolak commit yang menambah kredensial.\nexec php tools/rahasia.php staged\n");
    @chmod($f, 0755);
    gitleaks($root);
    info("Pre-commit hook terpasang: $f");
    return 0;
}

// --- main --------------------------------------------------------------------------
$argv = $_SERVER['argv'];
array_shift($argv);
$perintah = array_shift($argv) ?? '';
$opsi = [];
$pos = [];
for ($i = 0; $i < count($argv); $i++) {
    if (str_starts_with($argv[$i], '--')) {
        $k = substr($argv[$i], 2);
        $opsi[$k] = in_array($k, ['dasar', 'bukan-rahasia'], true) ? ($argv[++$i] ?? '') : true;
    } else {
        $pos[] = $argv[$i];
    }
}
[$rc, $root] = jalan(['git', 'rev-parse', '--show-toplevel']);
if ($rc !== 0) {
    keluar('Jalankan dari dalam repo Git.');
}
$root = trim($root);
chdir($root);
exit(match ($perintah) {
    'pindai' => pindai($root, $opsi),
    'perbaiki' => perbaiki($root, $opsi),
    'tandai' => $pos ? tandai($root, $pos, $opsi) : (keluar('Pakai: tandai <no...> --sudah-dirotasi | --bukan-rahasia "alasan"') ?? 2),
    'sidik' => $pos ? sidik($root, $pos) : (keluar('Pakai: sidik <no...>') ?? 2),
    'hook' => hook($root),
    'staged' => staged($root),
    default => (function () { echo implode('', array_slice(file(__FILE__), 2, 29)); return 2; })(),
});
