<?php

namespace App\Modules\PMC\WorkSnapshot\Jobs;

use App\Modules\PMC\WorkSnapshot\Contracts\WorkSlotSnapshotServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaptureSlotEndJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300, 900, 1800];

    public int $timeout = 60;

    public function __construct(
        public int $shiftId,
        public string $date,
    ) {}

    public function handle(WorkSlotSnapshotServiceInterface $service): void
    {
        $service->captureEnd($this->shiftId, $this->date);
    }

    public function failed(Throwable $e): void
    {
        Log::error('CaptureSlotEndJob exhausted retries', [
            'shift_id' => $this->shiftId,
            'date' => $this->date,
            'error' => $e->getMessage(),
        ]);
    }
}
