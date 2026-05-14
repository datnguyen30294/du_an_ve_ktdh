<?php

namespace App\Modules\PMC\WorkSnapshot\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\WorkSnapshot\Enums\SnapshotEntityTypeEnum;
use App\Modules\PMC\WorkSnapshot\Models\WorkSlotSnapshot;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class WorkSlotSnapshotRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new WorkSlotSnapshot);
    }

    /**
     * @return Collection<int, WorkSlotSnapshot>
     */
    public function getBySlot(int $accountId, string $date, int $shiftId): Collection
    {
        /** @var Collection<int, WorkSlotSnapshot> */
        return $this->newQuery()
            ->where('account_id', $accountId)
            ->whereDate('date', $date)
            ->where('shift_id', $shiftId)
            ->get();
    }

    /**
     * @return Collection<int, WorkSlotSnapshot>
     */
    public function getBySlotForShift(int $shiftId, string $date): Collection
    {
        /** @var Collection<int, WorkSlotSnapshot> */
        return $this->newQuery()
            ->where('shift_id', $shiftId)
            ->whereDate('date', $date)
            ->get();
    }

    public function existsForSlot(int $accountId, string $date, int $shiftId): bool
    {
        return $this->newQuery()
            ->where('account_id', $accountId)
            ->whereDate('date', $date)
            ->where('shift_id', $shiftId)
            ->exists();
    }

    /**
     * Upsert 1 snapshot row (idempotent via unique key).
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public function upsertRow(
        int $accountId,
        string $date,
        int $shiftId,
        SnapshotEntityTypeEnum $entityType,
        int $entityId,
        array $snapshotData,
        ?Carbon $capturedStartAt = null,
        ?Carbon $finalizedAt = null,
        bool $removedMidShift = false,
    ): WorkSlotSnapshot {
        $existing = $this->newQuery()
            ->where('account_id', $accountId)
            ->whereDate('date', $date)
            ->where('shift_id', $shiftId)
            ->where('entity_type', $entityType->value)
            ->where('entity_id', $entityId)
            ->first();

        $merged = $existing ? array_replace($existing->snapshot_data ?? [], $snapshotData) : $snapshotData;

        $attrs = [
            'account_id' => $accountId,
            'date' => $date,
            'shift_id' => $shiftId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'snapshot_data' => $merged,
        ];

        if ($capturedStartAt !== null) {
            $attrs['captured_start_at'] = $existing?->captured_start_at ?? $capturedStartAt;
        }
        if ($finalizedAt !== null) {
            $attrs['finalized_at'] = $finalizedAt;
        }
        if ($removedMidShift) {
            $attrs['removed_mid_shift'] = true;
        }

        if ($existing) {
            $existing->update($attrs);

            return $existing->refresh();
        }

        /** @var WorkSlotSnapshot */
        return $this->newQuery()->create($attrs);
    }

    /**
     * Slots with at least one row still unfinalized where the window end has passed.
     *
     * @return Collection<int, WorkSlotSnapshot>
     */
    public function findOverdueUnfinalized(Carbon $cutoff): Collection
    {
        /** @var Collection<int, WorkSlotSnapshot> */
        return $this->newQuery()
            ->whereNull('finalized_at')
            ->where('created_at', '<', $cutoff)
            ->get();
    }
}
