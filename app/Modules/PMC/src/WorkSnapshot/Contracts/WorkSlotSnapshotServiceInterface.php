<?php

namespace App\Modules\PMC\WorkSnapshot\Contracts;

interface WorkSlotSnapshotServiceInterface
{
    /**
     * Phase 1: capture WorkSchedule + active ticket assignees at shift start.
     */
    public function captureStart(int $shiftId, string $date): void;

    /**
     * Phase 2: merge-forward at shift end. Fills status_at_end for tickets,
     * marks removed_mid_shift for rows whose entity no longer exists,
     * inserts snapshot rows for entities that appeared mid-shift.
     */
    public function captureEnd(int $shiftId, string $date): void;

    /**
     * Build response for past slot detail (same shape as live getDetail).
     *
     * @return array<string, mixed>
     */
    public function getSlotDetail(int $accountId, string $date, int $shiftId): array;
}
