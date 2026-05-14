<?php

namespace App\Modules\PMC\WorkSnapshot\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use App\Modules\PMC\WorkSchedule\Repositories\WorkScheduleRepository;
use App\Modules\PMC\WorkSchedule\Services\TicketDerivationService;
use App\Modules\PMC\WorkSchedule\Services\TicketStatusHistoryService;
use App\Modules\PMC\WorkSnapshot\Contracts\WorkSlotSnapshotServiceInterface;
use App\Modules\PMC\WorkSnapshot\Enums\SnapshotEntityTypeEnum;
use App\Modules\PMC\WorkSnapshot\Repositories\WorkSlotSnapshotRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkSlotSnapshotService extends BaseService implements WorkSlotSnapshotServiceInterface
{
    public function __construct(
        protected WorkSlotSnapshotRepository $snapshotRepository,
        protected ShiftRepository $shiftRepository,
        protected WorkScheduleRepository $workScheduleRepository,
        protected OgTicketRepository $ogTicketRepository,
        protected TicketDerivationService $derivation,
        protected TicketStatusHistoryService $statusHistory,
    ) {}

    public function captureStart(int $shiftId, string $date): void
    {
        $shift = $this->shiftRepository->findById($shiftId);
        $day = Carbon::parse($date);
        [$shiftStart, $shiftEnd] = $this->derivation->shiftWindow($day, $shift);
        $capturedAt = Carbon::now();

        $wsRows = $this->workScheduleRepository->inRangeForAccounts(
            $this->allAccountIdsForSlot($date, $shiftId),
            $date,
            $date,
        )->filter(fn (WorkSchedule $ws) => (int) $ws->shift_id === $shiftId);

        foreach ($wsRows as $ws) {
            DB::transaction(fn () => $this->snapshotRepository->upsertRow(
                accountId: (int) $ws->account_id,
                date: $date,
                shiftId: $shiftId,
                entityType: SnapshotEntityTypeEnum::WorkSchedule,
                entityId: (int) $ws->id,
                snapshotData: $this->buildWorkScheduleSnapshot($ws),
                capturedStartAt: $capturedAt,
            ));
        }

        $ticketRows = $this->ogTicketRepository
            ->activeForAccountsInRange(
                $this->allAccountIdsForSlot($date, $shiftId),
                $shiftStart->toDateTimeString(),
                $shiftEnd->toDateTimeString(),
            )
            ->filter(fn ($row) => (int) ($row->project_id ?? 0) === (int) $shift->project_id);

        $ticketIds = $ticketRows->pluck('ticket_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $ticketsById = $ticketIds !== []
            ? $this->ogTicketRepository->findManyWithProject($ticketIds)->keyBy('id')
            : collect();

        $statusMap = $ticketIds !== []
            ? $this->statusHistory->batchGetStatusAt($ticketIds, $shiftStart)
            : [];

        foreach ($ticketRows as $row) {
            $ticketId = (int) $row->ticket_id;
            /** @var OgTicket|null $ticket */
            $ticket = $ticketsById[$ticketId] ?? null;
            if (! $ticket) {
                continue;
            }

            $statusAtStart = $this->resolveStatus($statusMap[$ticketId] ?? null, $ticket);
            if ($this->isTerminal($statusAtStart)) {
                continue;
            }

            DB::transaction(fn () => $this->snapshotRepository->upsertRow(
                accountId: (int) $row->account_id,
                date: $date,
                shiftId: $shiftId,
                entityType: SnapshotEntityTypeEnum::Ticket,
                entityId: $ticketId,
                snapshotData: $this->buildTicketSnapshot($ticket, $row, $statusAtStart, null),
                capturedStartAt: $capturedAt,
            ));
        }
    }

    public function captureEnd(int $shiftId, string $date): void
    {
        $shift = $this->shiftRepository->findById($shiftId);
        $day = Carbon::parse($date);
        [$shiftStart, $shiftEnd] = $this->derivation->shiftWindow($day, $shift);
        $finalizedAt = Carbon::now();

        $existingRows = $this->snapshotRepository->getBySlotForShift($shiftId, $date);

        $existingKeys = [];
        foreach ($existingRows as $row) {
            $existingKeys[$row->account_id][$row->entity_type->value][$row->entity_id] = $row;
        }

        $currentWsIds = [];
        $wsRows = $this->workScheduleRepository->inRangeForAccounts(
            $this->allAccountIdsForSlot($date, $shiftId),
            $date,
            $date,
        )->filter(fn (WorkSchedule $ws) => (int) $ws->shift_id === $shiftId);

        foreach ($wsRows as $ws) {
            $accountId = (int) $ws->account_id;
            $wsId = (int) $ws->id;
            $currentWsIds[$accountId][$wsId] = true;

            DB::transaction(fn () => $this->snapshotRepository->upsertRow(
                accountId: $accountId,
                date: $date,
                shiftId: $shiftId,
                entityType: SnapshotEntityTypeEnum::WorkSchedule,
                entityId: $wsId,
                snapshotData: $this->buildWorkScheduleSnapshot($ws),
                finalizedAt: $finalizedAt,
            ));
        }

        $ticketRows = $this->ogTicketRepository
            ->activeForAccountsInRange(
                $this->allAccountIdsForSlot($date, $shiftId),
                $shiftStart->toDateTimeString(),
                $shiftEnd->toDateTimeString(),
            )
            ->filter(fn ($row) => (int) ($row->project_id ?? 0) === (int) $shift->project_id);

        $ticketIds = $ticketRows->pluck('ticket_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
        $ticketsById = $ticketIds !== []
            ? $this->ogTicketRepository->findManyWithProject($ticketIds)->keyBy('id')
            : collect();

        $statusMap = $ticketIds !== []
            ? $this->statusHistory->batchGetStatusAt($ticketIds, $shiftEnd)
            : [];

        $currentTicketKeys = [];
        foreach ($ticketRows as $row) {
            $ticketId = (int) $row->ticket_id;
            $accountId = (int) $row->account_id;
            /** @var OgTicket|null $ticket */
            $ticket = $ticketsById[$ticketId] ?? null;
            if (! $ticket) {
                continue;
            }

            $statusAtEnd = $this->resolveStatus($statusMap[$ticketId] ?? null, $ticket);
            if ($this->isTerminal($statusAtEnd)) {
                continue;
            }

            $currentTicketKeys[$accountId][$ticketId] = true;

            $existingRow = $existingKeys[$accountId][SnapshotEntityTypeEnum::Ticket->value][$ticketId] ?? null;
            $statusAtStart = $existingRow->snapshot_data['status_at_start'] ?? null;

            DB::transaction(fn () => $this->snapshotRepository->upsertRow(
                accountId: $accountId,
                date: $date,
                shiftId: $shiftId,
                entityType: SnapshotEntityTypeEnum::Ticket,
                entityId: $ticketId,
                snapshotData: $this->buildTicketSnapshot($ticket, $row, $statusAtStart, $statusAtEnd),
                finalizedAt: $finalizedAt,
            ));
        }

        foreach ($existingRows as $row) {
            if ($row->finalized_at !== null) {
                continue;
            }
            $accountId = (int) $row->account_id;
            $entityId = (int) $row->entity_id;

            $stillExists = match ($row->entity_type) {
                SnapshotEntityTypeEnum::WorkSchedule => isset($currentWsIds[$accountId][$entityId]),
                SnapshotEntityTypeEnum::Ticket => isset($currentTicketKeys[$accountId][$entityId]),
                default => true,
            };

            if ($stillExists) {
                continue;
            }

            DB::transaction(fn () => $this->snapshotRepository->upsertRow(
                accountId: $accountId,
                date: $date,
                shiftId: $shiftId,
                entityType: $row->entity_type,
                entityId: $entityId,
                snapshotData: (array) $row->snapshot_data,
                finalizedAt: $finalizedAt,
                removedMidShift: true,
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSlotDetail(int $accountId, string $date, int $shiftId): array
    {
        $shift = $this->shiftRepository->findById($shiftId);
        $day = Carbon::parse($date);
        [$shiftStart, $shiftEnd] = $this->derivation->shiftWindow($day, $shift);

        $rows = $this->snapshotRepository->getBySlot($accountId, $date, $shiftId);

        $external = [];
        $tickets = [];
        $capturedStartAt = null;
        $finalizedAt = null;

        foreach ($rows as $row) {
            if ($row->captured_start_at && (! $capturedStartAt || $row->captured_start_at->lessThan($capturedStartAt))) {
                $capturedStartAt = $row->captured_start_at;
            }
            if ($row->finalized_at && (! $finalizedAt || $row->finalized_at->greaterThan($finalizedAt))) {
                $finalizedAt = $row->finalized_at;
            }

            $data = (array) $row->snapshot_data;

            if ($row->entity_type === SnapshotEntityTypeEnum::WorkSchedule) {
                $external[] = [
                    'id' => (int) $row->entity_id,
                    'project' => $data['project'] ?? null,
                    'note' => $data['note'] ?? null,
                    'external_ref' => $data['external_ref'] ?? null,
                    'removed_mid_shift' => (bool) $row->removed_mid_shift,
                    'source' => 'snapshot',
                ];

                continue;
            }

            if ($row->entity_type === SnapshotEntityTypeEnum::Ticket) {
                $statusAtSlot = $data['status_at_end'] ?? $data['status_at_start'] ?? null;
                $statusNow = $data['status_now'] ?? $statusAtSlot;
                $tickets[] = [
                    'id' => (int) $row->entity_id,
                    'subject' => $data['subject'] ?? '',
                    'project' => $data['project'] ?? null,
                    'priority' => $data['priority'] ?? null,
                    'assigned_at' => $data['assigned_at'] ?? null,
                    'status_at_slot' => $statusAtSlot,
                    'status_now' => $statusNow,
                    'is_status_changed' => $statusAtSlot && $statusNow
                        ? ($statusAtSlot['value'] ?? null) !== ($statusNow['value'] ?? null)
                        : false,
                    'removed_mid_shift' => (bool) $row->removed_mid_shift,
                    'source' => 'snapshot',
                ];
            }
        }

        return [
            'account' => null,
            'date' => $date,
            'shift' => [
                'id' => (int) $shift->id,
                'project_id' => (int) $shift->project_id,
                'code' => (string) $shift->code,
                'name' => (string) $shift->name,
                'start_time' => substr((string) $shift->start_time, 0, 5),
                'end_time' => substr((string) $shift->end_time, 0, 5),
                'is_overnight' => $shift->isOvernight(),
            ],
            'shift_window' => [
                'start' => $shiftStart->toIso8601String(),
                'end' => $shiftEnd->toIso8601String(),
            ],
            'external' => $external,
            'tickets' => $tickets,
            'orders' => [],
            'data_source' => 'snapshot',
            'captured_start_at' => $capturedStartAt?->toIso8601String(),
            'finalized_at' => $finalizedAt?->toIso8601String(),
        ];
    }

    /**
     * Scan sweep: return overdue unfinalized (shift_id, date) pairs.
     *
     * @return list<array{shift_id: int, date: string}>
     */
    public function getOverduePairs(int $thresholdMinutes = 30): array
    {
        $cutoff = Carbon::now()->subMinutes($thresholdMinutes);
        $rows = $this->snapshotRepository->findOverdueUnfinalized($cutoff);
        $seen = [];
        $pairs = [];
        foreach ($rows as $row) {
            $date = $row->date instanceof \DateTimeInterface
                ? $row->date->format('Y-m-d')
                : (string) $row->date;
            $key = $row->shift_id.'|'.$date;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $pairs[] = ['shift_id' => (int) $row->shift_id, 'date' => $date];
        }

        return $pairs;
    }

    /**
     * Collect candidate account IDs for this slot — union of:
     *  - accounts with WorkSchedule for (shift, date)
     *  - accounts referenced in existing snapshot rows
     *  - accounts with ticket assignment in shift.project_id overlapping the window
     *
     * @return list<int>
     */
    protected function allAccountIdsForSlot(string $date, int $shiftId): array
    {
        $shift = $this->shiftRepository->findById($shiftId);
        $day = Carbon::parse($date);
        [$shiftStart, $shiftEnd] = $this->derivation->shiftWindow($day, $shift);

        $ws = WorkSchedule::query()
            ->whereDate('date', $date)
            ->where('shift_id', $shiftId)
            ->pluck('account_id');

        $existing = $this->snapshotRepository
            ->getBySlotForShift($shiftId, $date)
            ->pluck('account_id');

        $assigned = DB::table('og_ticket_assignees as a')
            ->join('og_tickets as t', 't.id', '=', 'a.og_ticket_id')
            ->where('t.project_id', (int) $shift->project_id)
            ->whereNull('t.deleted_at')
            ->where('a.created_at', '<=', $shiftEnd->toDateTimeString())
            ->where(function ($q) use ($shiftStart): void {
                $q->whereNull('t.completed_at')
                    ->orWhere('t.completed_at', '>=', $shiftStart->toDateTimeString());
            })
            ->distinct()
            ->pluck('a.account_id');

        return $ws->merge($existing)->merge($assigned)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildWorkScheduleSnapshot(WorkSchedule $ws): array
    {
        $ws->loadMissing('project');

        return [
            'work_schedule_id' => (int) $ws->id,
            'project' => $ws->project ? [
                'id' => (int) $ws->project->id,
                'code' => $ws->project->code,
                'name' => (string) $ws->project->name,
            ] : null,
            'note' => $ws->note,
            'external_ref' => $ws->external_ref,
        ];
    }

    /**
     * @param  array{value: string, label: string}|null  $statusAtStart
     * @param  array{value: string, label: string}|null  $statusAtEnd
     * @return array<string, mixed>
     */
    protected function buildTicketSnapshot(OgTicket $ticket, object $row, ?array $statusAtStart, ?array $statusAtEnd): array
    {
        $data = [
            'ticket_id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'project' => $ticket->project ? [
                'id' => (int) $ticket->project->id,
                'name' => (string) $ticket->project->name,
            ] : null,
            'priority' => [
                'value' => $ticket->priority->value,
                'label' => $ticket->priority->label(),
            ],
            'assigned_at' => Carbon::parse($row->assigned_at)->toIso8601String(),
            'status_now' => [
                'value' => $ticket->status->value,
                'label' => $ticket->status->label(),
            ],
        ];

        if ($statusAtStart !== null) {
            $data['status_at_start'] = $statusAtStart;
        }
        if ($statusAtEnd !== null) {
            $data['status_at_end'] = $statusAtEnd;
        }

        return $data;
    }

    /**
     * @return array{value: string, label: string}
     */
    protected function resolveStatus(?string $historical, OgTicket $ticket): array
    {
        $value = $historical ?? $ticket->status->value;
        $enum = OgTicketStatus::from($value);

        return ['value' => $enum->value, 'label' => $enum->label()];
    }

    /**
     * @param  array{value: string, label: string}  $status
     */
    protected function isTerminal(array $status): bool
    {
        return in_array($status['value'] ?? null, [
            OgTicketStatus::Rejected->value,
            OgTicketStatus::Cancelled->value,
        ], true);
    }
}
