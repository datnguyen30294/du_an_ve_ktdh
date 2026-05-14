<?php

namespace App\Modules\PMC\WorkSchedule\Services;

use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Shift\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TicketDerivationService
{
    public function __construct(
        protected OgTicketRepository $ogTicketRepository,
    ) {}

    /**
     * Aggregate ticket counts per (account, date, shift). All shifts contribute to derivation.
     *
     * Returns: `slots[account_id][YYYY-MM-DD][shift_id] = int (count)`.
     *
     * @param  list<int>  $accountIds
     * @param  Collection<int, Shift>  $shifts
     * @return array<int, array<string, array<int, int>>>
     */
    public function deriveSlots(array $accountIds, Carbon $from, Carbon $to, Collection $shifts): array
    {
        $slots = [];

        if ($accountIds === [] || $shifts->isEmpty()) {
            return $slots;
        }

        /** @var array<int, list<Shift>> $shiftsByProject */
        $shiftsByProject = [];
        foreach ($shifts as $shift) {
            $shiftsByProject[(int) $shift->project_id][] = $shift;
        }

        $rangeStart = $from->copy()->startOfDay();
        $rangeEnd = $to->copy()->endOfDay();

        $rows = $this->ogTicketRepository->activeForAccountsInRange(
            $accountIds,
            $rangeStart->toDateTimeString(),
            $rangeEnd->toDateTimeString(),
        );

        $todayEnd = CarbonImmutable::now()->endOfDay();

        foreach ($rows as $row) {
            $projectId = $row->project_id !== null ? (int) $row->project_id : null;
            if ($projectId === null || ! isset($shiftsByProject[$projectId])) {
                continue;
            }

            $accountId = (int) $row->account_id;
            $assignedAt = Carbon::parse($row->assigned_at);
            $completedAt = $row->completed_at ? Carbon::parse($row->completed_at) : null;

            $slotStart = $assignedAt->greaterThan($rangeStart) ? $assignedAt->copy() : $rangeStart->copy();
            $slotEndCandidate = $completedAt ?? $todayEnd;
            $slotEnd = $slotEndCandidate->lessThan($rangeEnd) ? $slotEndCandidate->copy() : $rangeEnd->copy();

            if ($slotStart->greaterThan($slotEnd)) {
                continue;
            }

            $cursor = $slotStart->copy()->startOfDay();
            $stop = $slotEnd->copy()->startOfDay();

            while ($cursor->lessThanOrEqualTo($stop)) {
                $dateKey = $cursor->format('Y-m-d');

                foreach ($shiftsByProject[$projectId] as $shift) {
                    [$shiftStart, $shiftEnd] = $this->shiftWindow($cursor, $shift);

                    if ($assignedAt->greaterThan($shiftEnd)) {
                        continue;
                    }
                    if ($completedAt && $completedAt->lessThan($shiftStart)) {
                        continue;
                    }

                    $slots[$accountId][$dateKey][(int) $shift->id] =
                        ($slots[$accountId][$dateKey][(int) $shift->id] ?? 0) + 1;
                }

                $cursor->addDay();
            }
        }

        return $slots;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function shiftWindow(Carbon $date, Shift $shift): array
    {
        $start = $date->copy()->setTimeFromTimeString($this->normalizeTime((string) $shift->start_time));
        $end = $date->copy()->setTimeFromTimeString($this->normalizeTime((string) $shift->end_time));

        if ($shift->isOvernight()) {
            $end->addDay();
        }

        return [$start, $end];
    }

    protected function normalizeTime(string $value): string
    {
        if ($value === '') {
            return '00:00:00';
        }

        $tail = substr($value, -8);

        return strlen($tail) === 8 ? $tail : $value;
    }
}
