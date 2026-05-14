<?php

namespace App\Modules\PMC\WorkSnapshot\Commands;

use App\Modules\PMC\WorkSnapshot\Jobs\CaptureSlotEndJob;
use App\Modules\PMC\WorkSnapshot\Services\WorkSlotSnapshotService;
use Illuminate\Console\Command;

class SweepUnfinalizedSnapshotsCommand extends Command
{
    protected $signature = 'snapshot:sweep-unfinalized {--threshold=30 : Minute threshold past the row creation}';

    protected $description = 'Re-dispatch capture-end jobs for snapshot rows still unfinalized past threshold.';

    public function handle(WorkSlotSnapshotService $service): int
    {
        $threshold = (int) $this->option('threshold');
        $pairs = $service->getOverduePairs($threshold);

        foreach ($pairs as $pair) {
            CaptureSlotEndJob::dispatch($pair['shift_id'], $pair['date']);
            $this->info("Re-dispatched end capture: shift={$pair['shift_id']} date={$pair['date']}");
        }

        $this->info('Sweep done. Overdue pairs: '.count($pairs));

        return self::SUCCESS;
    }
}
