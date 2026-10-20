<?php

namespace App\Console\Commands;

use App\Support\Brand;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Membuat ulang varian logo (sidebar, ikon tab, PDF, favicon.ico) dari logo sumber
 * config('app.logo'). Jalankan setelah mengganti file logo; build image Docker juga
 * menjalankannya otomatis.
 */
class BrandSync extends Command
{
    protected $signature = 'brand:sync';

    protected $description = 'Buat ulang varian logo dari logo sumber (config app.logo)';

    public function handle(): int
    {
        try {
            $written = Brand::generate();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($written as $file => $bytes) {
            $this->line(sprintf('  %-22s %7.1f KB', $file, $bytes / 1024));
        }
        $this->info('Logo sumber: public/' . Brand::source() . ' → ' . Brand::url());

        return self::SUCCESS;
    }
}
