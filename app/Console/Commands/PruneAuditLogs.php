<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Memangkas audit log lama agar tabel tidak tumbuh tanpa batas.
 * Default simpan 180 hari; bisa di-override: `audit:prune --days=90`.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune {--days=180}';

    protected $description = 'Hapus audit log yang lebih lama dari N hari';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = AuditLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Menghapus {$deleted} audit log lebih lama dari {$days} hari.");

        return self::SUCCESS;
    }
}
