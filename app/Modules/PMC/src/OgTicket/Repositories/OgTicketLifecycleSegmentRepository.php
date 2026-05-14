<?php

namespace App\Modules\PMC\OgTicket\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;

class OgTicketLifecycleSegmentRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OgTicketLifecycleSegment);
    }

    public function findActiveSegment(int $ogTicketId): ?OgTicketLifecycleSegment
    {
        /** @var OgTicketLifecycleSegment|null */
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->whereNull('ended_at')
            ->first();
    }

    /**
     * Đóng segment đang active (set ended_at = now).
     */
    public function closeSegment(int $segmentId): bool
    {
        return $this->update($segmentId, ['ended_at' => now()]);
    }

    /**
     * Confirm tất cả segments pending của 1 cycle.
     */
    public function confirmCycle(int $ogTicketId, int $cycle): int
    {
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('cycle', $cycle)
            ->where('cycle_confirmed', false)
            ->update(['cycle_confirmed' => true]);
    }
}
