<?php

namespace App\Modules\PMC\WorkSchedule\Services;

use App\Modules\PMC\OgTicket\Models\OgTicket;
use Carbon\Carbon;
use OwenIt\Auditing\Models\Audit;

class TicketStatusHistoryService
{
    /**
     * Return the reconstructed status for each ticket id at the given cutoff moment.
     * For tickets with no audit row before the cutoff, the status is omitted — caller
     * should fall back to the ticket's current status.
     *
     * @param  list<int>  $ticketIds
     * @return array<int, string>
     */
    public function batchGetStatusAt(array $ticketIds, Carbon $at): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $audits = Audit::query()
            ->where('auditable_type', OgTicket::class)
            ->whereIn('auditable_id', $ticketIds)
            ->where('created_at', '<=', $at)
            ->orderBy('auditable_id')
            ->orderByDesc('created_at')
            ->get(['auditable_id', 'new_values', 'created_at']);

        $statusByTicket = [];

        foreach ($audits as $audit) {
            $ticketId = (int) $audit->auditable_id;

            if (isset($statusByTicket[$ticketId])) {
                continue;
            }

            $values = $audit->new_values;
            if (is_array($values) && ! empty($values['status'])) {
                $statusByTicket[$ticketId] = (string) $values['status'];
            }
        }

        return $statusByTicket;
    }

    public function getStatusAt(int $ticketId, Carbon $at): ?string
    {
        return $this->batchGetStatusAt([$ticketId], $at)[$ticketId] ?? null;
    }
}
