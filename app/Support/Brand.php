<?php

namespace App\Support;

use RuntimeException;

/**
 * Logo aplikasi dari SATU sumber: config('app.logo') — default public/Logo_Baru.png.
 *
 * Setiap URL logo memuat versi dari ISI file (?v=…). nginx menyimpan gambar statis 7 hari
 * di browser, jadi tanpa versi logo lama bisa bertahan seminggu. Dengan versi, mengganti
 * file logo lalu rilis langsung mengganti logo di semua browser pada pemuatan halaman
 * berikutnya — tanpa hapus cache.
 *
 * Varian ringan (public/brand/*) dibuat dari logo sumber oleh `php artisan brand:sync`
 * dan hanya dipakai bila dibuat dari logo yang SAMA (hash di manifest). Bila logo sumber
 * sudah diganti tetapi varian belum dibuat ulang, otomatis dipakai file sumber — logo
 * lama tidak pernah tampil.
 */
final class Brand
{
    /** Varian: nama => [ukuran px, bentuk]. fit = lebar tetap; square = kanvas persegi. */
    public const VARIANTS = [
        'logo'     => [480, 'fit'],           // halaman login, kop cetak
        'logo-sm'  => [96, 'fit'],            // sidebar & top bar (tampil ±40px)
        'pdf'      => [160, 'fit'],           // kop PDF (dompdf)
        'icon-32'  => [32, 'square'],         // ikon tab browser
        'icon-180' => [180, 'square-solid'],  // ikon layar utama iPhone/iPad (tanpa transparansi)
    ];

    private const DIR = 'brand';

    private static ?array $state = null;

    public static function source(): string
    {
        return ltrim((string) config('app.logo', 'Logo_Baru.png'), '/');
    }

    /** URL logo (varian ringan bila sesuai logo sumber), selalu berversi isi file. */
    public static function url(string $variant = 'logo'): string
    {
        $s    = self::state();
        $file = self::variantFile($variant, $s) ?? $s['source'];

        return asset($file) . ($s['hash'] ? '?v=' . substr($s['hash'], 0, 12) : '');
    }

    /** Path file di disk — untuk dompdf, yang membaca gambar dari disk, bukan URL. */
    public static function path(string $variant = 'logo'): string
    {
        $s = self::state();

        return public_path(self::variantFile($variant, $s) ?? $s['source']);
    }

    /**
     * Buat ulang semua varian & public/favicon.ico dari logo sumber.
     *
     * @return array<string, int> nama file => ukuran byte
     */
    public static function generate(): array
    {
        if (! function_exists('imagecreatefrompng')) {
            throw new RuntimeException('Ekstensi PHP GD tidak tersedia.');
        }
        $srcFile = public_path(self::source());
        if (! is_file($srcFile)) {
            throw new RuntimeException("Logo sumber tidak ditemukan: {$srcFile}");
        }
        $img = @imagecreatefrompng($srcFile);
        if (! $img) {
            throw new RuntimeException('Logo sumber harus berformat PNG: ' . self::source());
        }
        imagesavealpha($img, true);

        $dir = public_path(self::DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = [];
        foreach (self::VARIANTS as $name => [$size, $shape]) {
            $written[self::DIR . "/{$name}.png"] = self::writeAtomic("{$dir}/{$name}.png", self::png(self::render($img, $size, $shape)));
        }
        // /favicon.ico tetap diminta sebagian browser & alat di luar halaman HTML.
        $ico = [];
        foreach ([16, 32, 48] as $size) {
            $ico[$size] = self::png(self::render($img, $size, 'square'));
        }
        $written['favicon.ico'] = self::writeAtomic(public_path('favicon.ico'), self::ico($ico));
        imagedestroy($img);

        self::writeAtomic("{$dir}/manifest.json", json_encode([
            'source' => self::source(),
            'hash'   => md5_file($srcFile),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        self::$state = null;

        return $written;
    }

    private static function variantFile(string $variant, array $s): ?string
    {
        $file = self::DIR . "/{$variant}.png";

        return $s['fresh'] && isset(self::VARIANTS[$variant]) && is_file(public_path($file)) ? $file : null;
    }

    /** Hash logo sumber & apakah varian dibuat dari logo yang sama; dihitung ulang bila file berubah. */
    private static function state(): array
    {
        $source = self::source();
        $file   = public_path($source);
        $key    = $source . '|' . (is_file($file) ? filemtime($file) . ':' . filesize($file) : '-');
        if (self::$state !== null && self::$state['key'] === $key) {
            return self::$state;
        }
        $hash     = is_file($file) ? md5_file($file) : null;
        $manifest = json_decode((string) @file_get_contents(public_path(self::DIR . '/manifest.json')), true) ?: [];

        return self::$state = [
            'key'    => $key,
            'source' => $source,
            'hash'   => $hash,
            'fresh'  => $hash !== null && ($manifest['source'] ?? null) === $source && ($manifest['hash'] ?? null) === $hash,
        ];
    }

    /** @param \GdImage $img */
    private static function render($img, int $size, string $shape)
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        if ($shape === 'fit') {
            [$cw, $ch] = [$size, (int) round($h * $size / $w)];
            [$dx, $dy, $dw, $dh] = [0, 0, $cw, $ch];
        } else {
            $cw = $ch = $size;
            $pad   = $shape === 'square-solid' ? (int) round($size * 0.08) : 0;
            $scale = min(($size - 2 * $pad) / $w, ($size - 2 * $pad) / $h);
            [$dw, $dh] = [(int) round($w * $scale), (int) round($h * $scale)];
            [$dx, $dy] = [intdiv($size - $dw, 2), intdiv($size - $dh, 2)];
        }

        $canvas = imagecreatetruecolor($cw, $ch);
        if ($shape === 'square-solid') {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagealphablending($canvas, true);
        } else {
            imagealphablending($canvas, false);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $img, $dx, $dy, 0, 0, $dw, $dh, $w, $h);

        return $canvas;
    }

    /** @param \GdImage $canvas */
    private static function png($canvas): string
    {
        ob_start();
        imagepng($canvas, null, 9);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }

    /** Berkas .ico berisi gambar PNG (didukung semua browser modern). */
    private static function ico(array $pngs): string
    {
        $offset = 6 + 16 * count($pngs);
        [$entries, $data] = ['', ''];
        foreach ($pngs as $size => $png) {
            $entries .= pack('CCCCvvVV', $size % 256, $size % 256, 0, 0, 1, 32, strlen($png), $offset);
            $offset  += strlen($png);
            $data    .= $png;
        }

        return pack('vvv', 0, 1, count($pngs)) . $entries . $data;
    }

    private static function writeAtomic(string $path, string $bytes): int
    {
        $tmp = $path . '.tmp-' . getmypid();
        if (file_put_contents($tmp, $bytes) === false || ! rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Gagal menulis {$path}");
        }

        return strlen($bytes);
    }
}
