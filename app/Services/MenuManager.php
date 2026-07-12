<?php

namespace App\Services;

use App\Models\MenuSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Aktivasi/deaktivasi menu sidebar per role (dikelola Tim IT).
 *
 * Katalog menu bersifat kanonik di sini; status enable/disable disimpan di
 * tabel menu_settings. Default: SEMUA menu aktif (baris hanya dibuat saat
 * dinonaktifkan/diubah). Hasil di-cache agar ringan.
 */
class MenuManager
{
    /** Role yang menunya dapat dikelola Tim IT. */
    public const MANAGED_ROLES = ['master', 'daerah'];

    private const CACHE_KEY = 'menu_settings:map';

    /**
     * Katalog menu per role: key => label. Urutan mengikuti sidebar.
     *
     * @return array<string, array<string, string>>
     */
    public function catalog(): array
    {
        return [
            'master' => [
                'dashboard'     => 'Dashboard',
                'presentasi'    => 'Ikhtisar Eksekutif',
                'laporan'       => 'Input Laporan',
                'statistik'     => 'Statistik',
                'matrix'        => 'Matriks',
                'import'         => 'Import OPPKPKE',
                'import_rat'     => 'Import RAT',
                'import_hierarki' => 'Import Hierarki',
                'export'         => 'Export (Excel & PDF)',
                'users'         => 'Kelola Pengguna',
                'announcements' => 'Pengumuman',
                'chat'          => 'Pantau Chat',
                'sessions'      => 'Sesi Login',
                'panduan'       => 'Panduan Penggunaan',
            ],
            'daerah' => [
                'dashboard'       => 'Beranda',
                'laporan'         => 'Input Data',
                'report'          => 'Rekap Laporan',
                'chat'            => 'Chat Support IT',
                'panduan'         => 'Panduan',
                'pic'             => 'Identitas PIC',
                'change_password' => 'Ganti Password',
            ],
        ];
    }

    public function roleLabel(string $role): string
    {
        return [
            'master' => 'Admin Master',
            'daerah' => 'Operator Daerah',
        ][$role] ?? $role;
    }

    /** Apakah sebuah menu aktif untuk role tsb (default: aktif). */
    public function isEnabled(string $role, string $key): bool
    {
        $map = $this->map();

        return $map[$role][$key] ?? true;
    }

    /**
     * Status semua menu untuk sebuah role: [key => bool].
     *
     * @return array<string, bool>
     */
    public function statesFor(string $role): array
    {
        $states = [];
        foreach (array_keys($this->catalog()[$role] ?? []) as $key) {
            $states[$key] = $this->isEnabled($role, $key);
        }

        return $states;
    }

    /**
     * Simpan status menu untuk sebuah role dari daftar key yang AKTIF (dicentang).
     * Key di katalog yang tidak ada di $enabledKeys → dinonaktifkan.
     *
     * @param  array<int, string>  $enabledKeys
     */
    public function saveRole(string $role, array $enabledKeys): void
    {
        $enabledKeys = array_flip($enabledKeys);
        foreach (array_keys($this->catalog()[$role] ?? []) as $key) {
            MenuSetting::updateOrCreate(
                ['role' => $role, 'menu_key' => $key],
                ['is_enabled' => isset($enabledKeys[$key])],
            );
        }
        $this->clearCache();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Peta status dari DB: [role][key] => bool. Di-cache.
     *
     * @return array<string, array<string, bool>>
     */
    private function map(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            $map = [];
            foreach (MenuSetting::all() as $row) {
                $map[$row->role][$row->menu_key] = (bool) $row->is_enabled;
            }

            return $map;
        });
    }
}
