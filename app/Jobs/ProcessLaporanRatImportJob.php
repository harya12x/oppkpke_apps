<?php

namespace App\Jobs;

use App\Services\OppkpkeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLaporanRatImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private array $cached,
        private array $skip,
        private bool $replaceYear,
        private int $userId,
        private string $statusKey,
    ) {}

    public function handle(OppkpkeService $service): void
    {
        try {
            $result = $service->executeRatImport($this->cached, $this->skip, $this->replaceYear, $this->userId);

            cache()->put($this->statusKey, array_merge(['state' => 'done'], $result), now()->addHour());

            Log::channel('audit')->info('Import RAT selesai', array_merge(['user_id' => $this->userId], $result));
        } catch (\Throwable $e) {
            cache()->put($this->statusKey, [
                'state'   => 'failed',
                'message' => 'Import gagal, silakan coba lagi atau hubungi admin.',
            ], now()->addHour());

            Log::channel('audit')->error('Import RAT gagal', ['user_id' => $this->userId, 'error' => $e->getMessage()]);

            throw $e;
        }
    }
}
