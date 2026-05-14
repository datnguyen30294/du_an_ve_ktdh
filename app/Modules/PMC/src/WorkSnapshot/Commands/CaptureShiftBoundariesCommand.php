<?php

namespace App\Modules\PMC\WorkSnapshot\Commands;

use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use App\Modules\PMC\WorkSnapshot\Jobs\CaptureSlotEndJob;
use App\Modules\PMC\WorkSnapshot\Jobs\CaptureSlotStartJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CaptureShiftBoundariesCommand extends Command
{
    protected $signature = 'snapshot:capture-shift-boundaries';

    protected $description = 'Dispatch snapshot capture jobs at shift start/end boundaries (every minute).';

    public function handle(ShiftRepository $shiftRepository): int
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');
        $hm = $now->format('H:i');
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        foreach ($shiftRepository->findByStartTime($hm) as $shift) {
            CaptureSlotStartJob::dispatch((int) $shift->id, $today);
            $this->info("Dispatched start capture: shift={$shift->id} date={$today}");
        }

        foreach ($shiftRepository->findByEndTime($hm) as $shift) {
            $slotDate = $shift->isOvernight() ? $yesterday : $today;
            CaptureSlotEndJob::dispatch((int) $shift->id, $slotDate);
            $this->info("Dispatched end capture: shift={$shift->id} date={$slotDate}");
        }

        return self::SUCCESS;
    }
}
