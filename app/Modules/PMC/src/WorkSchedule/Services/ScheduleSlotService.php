<?php

namespace App\Modules\PMC\WorkSchedule\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Repositories\OgTicketRepository;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use App\Modules\PMC\WorkSchedule\Contracts\ScheduleSlotServiceInterface;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use App\Modules\PMC\WorkSchedule\Repositories\WorkScheduleRepository;
use App\Modules\PMC\WorkSnapshot\Contracts\WorkSlotSnapshotServiceInterface;
use App\Modules\PMC\WorkSnapshot\Repositories\WorkSlotSnapshotRepository;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ScheduleSlotService extends BaseService implements ScheduleSlotServiceInterface
{
    private const TEAM_ACCOUNT_HARD_LIMIT = 500;

    public function __construct(
        protected AccountRepository $accountRepository,
        protected ShiftRepository $shiftRepository,
        protected WorkScheduleRepository $workScheduleRepository,
        protected OgTicketRepository $ogTicketRepository,
        protected TicketDerivationService $derivation,
        protected TicketStatusHistoryService $statusHistory,
        protected WorkSlotSnapshotRepository $snapshotRepository,
        protected WorkSlotSnapshotServiceInterface $snapshotService,
    ) {}

    /**
     * Personal calendar: one card per (shift, project) per day the account has activity.
     *
     * @return array<string, mixed>
     */
    public function getPersonal(int $accountId, string $month): array
    {
        /** @var Account $account */
        $account = $this->accountRepository->findById($accountId);
        [$from, $to] = $this->monthRange($month);

        $shifts = $this->shiftRepository->all()->loadMissing('project')->keyBy('id');

        $days = $this->buildDays($from, $to);

        $externalRows = $this->workScheduleRepository->inRangeForAccounts(
            [$accountId],
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        $ticketSlots = $this->derivation->deriveSlots([$accountId], $from, $to, $shifts->values());
        $slotsForAccount = $ticketSlots[$accountId] ?? [];

        $wsByDateShift = [];
        foreach ($externalRows as $ws) {
            $dateKey = $ws->date instanceof \DateTimeInterface
                ? $ws->date->format('Y-m-d')
                : (string) $ws->date;
            $shiftId = (int) $ws->shift_id;
            $wsByDateShift[$dateKey][$shiftId] = true;
        }

        $dayCards = [];
        foreach ($days as $day) {
            $dateKey = $day['date'];
            $candidateIds = array_values(array_unique(array_merge(
                array_keys($wsByDateShift[$dateKey] ?? []),
                array_keys($slotsForAccount[$dateKey] ?? []),
            )));

            $cards = [];
            foreach ($candidateIds as $shiftId) {
                $shift = $shifts->get($shiftId);
                if (! $shift) {
                    continue;
                }

                $cards[] = [
                    'shift' => $this->shiftSummary($shift),
                    'project' => $this->projectSummary($shift->project),
                    'has_workschedule' => (bool) ($wsByDateShift[$dateKey][$shiftId] ?? false),
                    'ticket_count' => (int) ($slotsForAccount[$dateKey][$shiftId] ?? 0),
                ];
            }

            usort($cards, function (array $a, array $b): int {
                $s = strcmp($a['shift']['start_time'], $b['shift']['start_time']);
                if ($s !== 0) {
                    return $s;
                }
                $aName = (string) ($a['project']['name'] ?? '');
                $bName = (string) ($b['project']['name'] ?? '');

                return strcmp($aName, $bName);
            });

            $dayCards[$dateKey] = $cards;
        }

        return [
            'month' => $month,
            'account' => $this->accountSummary($account),
            'days' => $days,
            'day_cards' => empty($dayCards) ? new \stdClass : $dayCards,
        ];
    }

    /**
     * Team calendar: day_cards per account, aggregating shifts across all the account's projects.
     * When $strictProject is true and $projectId is set, cards are limited to shifts belonging to that project.
     *
     * @param  list<int>|null  $accountIds
     * @return array<string, mixed>
     */
    public function getTeam(string $month, ?int $projectId, ?array $accountIds, bool $strictProject = false): array
    {
        [$from, $to] = $this->monthRange($month);

        $accounts = $this->accountRepository->listActiveForTeamSchedule($projectId, $accountIds);

        if ($accounts->count() > self::TEAM_ACCOUNT_HARD_LIMIT) {
            throw new BusinessException(
                'Quá nhiều nhân viên. Vui lòng lọc theo dự án.',
                'TEAM_VIEW_TOO_MANY_ACCOUNTS',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['count' => $accounts->count()],
            );
        }

        $shifts = ($strictProject && $projectId !== null)
            ? $this->shiftRepository->allForProject($projectId)->loadMissing('project')->keyBy('id')
            : $this->shiftRepository->all()->loadMissing('project')->keyBy('id');
        $days = $this->buildDays($from, $to);

        /** @var list<int> $resolvedAccountIds */
        $resolvedAccountIds = $accounts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $externalRows = $this->workScheduleRepository->inRangeForAccounts(
            $resolvedAccountIds,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        );

        $ticketSlots = $this->derivation->deriveSlots($resolvedAccountIds, $from, $to, $shifts->values());

        $wsByAccountDateShift = [];
        foreach ($externalRows as $ws) {
            $dateKey = $ws->date instanceof \DateTimeInterface
                ? $ws->date->format('Y-m-d')
                : (string) $ws->date;
            $aid = (int) $ws->account_id;
            $sid = (int) $ws->shift_id;
            $wsByAccountDateShift[$aid][$dateKey][$sid] = true;
        }

        $dayCardsByAccount = [];
        foreach ($resolvedAccountIds as $aid) {
            $wsByDate = $wsByAccountDateShift[$aid] ?? [];
            $slotsForAccount = $ticketSlots[$aid] ?? [];
            $dayCards = [];
            foreach ($days as $day) {
                $dateKey = $day['date'];
                $candidateIds = array_values(array_unique(array_merge(
                    array_keys($wsByDate[$dateKey] ?? []),
                    array_keys($slotsForAccount[$dateKey] ?? []),
                )));

                $cards = [];
                foreach ($candidateIds as $shiftId) {
                    $shift = $shifts->get($shiftId);
                    if (! $shift) {
                        continue;
                    }
                    $cards[] = [
                        'shift' => $this->shiftSummary($shift),
                        'project' => $this->projectSummary($shift->project),
                        'has_workschedule' => (bool) ($wsByDate[$dateKey][$shiftId] ?? false),
                        'ticket_count' => (int) ($slotsForAccount[$dateKey][$shiftId] ?? 0),
                    ];
                }

                usort($cards, function (array $a, array $b): int {
                    $s = strcmp($a['shift']['start_time'], $b['shift']['start_time']);
                    if ($s !== 0) {
                        return $s;
                    }
                    $aName = (string) ($a['project']['name'] ?? '');
                    $bName = (string) ($b['project']['name'] ?? '');

                    return strcmp($aName, $bName);
                });

                $dayCards[$dateKey] = $cards;
            }
            $dayCardsByAccount[$aid] = $dayCards;
        }

        return [
            'month' => $month,
            'project_id' => $projectId,
            'accounts' => $accounts->map(fn (Account $a) => $this->accountSummary($a))->values()->all(),
            'days' => $days,
            'day_cards_by_account' => empty($dayCardsByAccount) ? new \stdClass : $dayCardsByAccount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(int $accountId, string $date, int $shiftId): array
    {
        /** @var Account $account */
        $account = $this->accountRepository->findById($accountId);
        /** @var Shift $shift */
        $shift = $this->shiftRepository->findById($shiftId);

        $day = Carbon::parse($date);

        if ($day->lessThan(Carbon::today()) && $this->snapshotRepository->existsForSlot($accountId, $date, $shiftId)) {
            $snapshot = $this->snapshotService->getSlotDetail($accountId, $date, $shiftId);
            $snapshot['account'] = $this->accountSummary($account);

            return $snapshot;
        }

        [$shiftStart, $shiftEnd] = $this->derivation->shiftWindow($day, $shift);

        $externalRows = $this->workScheduleRepository->inRangeForAccounts(
            [$accountId],
            $date,
            $date,
        )->filter(fn (WorkSchedule $ws) => (int) $ws->shift_id === $shiftId);

        $tickets = [];

        $rows = $this->ogTicketRepository->activeForAccountsInRange(
            [$accountId],
            $shiftStart->toDateTimeString(),
            $shiftEnd->toDateTimeString(),
        )->filter(fn ($row) => (int) ($row->project_id ?? 0) === (int) $shift->project_id);

        $ticketIds = $rows->pluck('ticket_id')->map(fn ($v) => (int) $v)->unique()->values()->all();

        if ($ticketIds !== []) {
            $useAudit = $shiftEnd->lessThan(Carbon::now());
            $statusMap = $useAudit
                ? $this->statusHistory->batchGetStatusAt($ticketIds, $shiftEnd)
                : [];

            $ticketsById = $this->ogTicketRepository->findManyWithProject($ticketIds)->keyBy('id');

            foreach ($rows as $row) {
                $ticketId = (int) $row->ticket_id;
                /** @var OgTicket|null $ticket */
                $ticket = $ticketsById[$ticketId] ?? null;

                if (! $ticket) {
                    continue;
                }

                $statusAtValue = $statusMap[$ticketId] ?? $ticket->status->value;

                if (in_array($statusAtValue, [
                    OgTicketStatus::Rejected->value,
                    OgTicketStatus::Cancelled->value,
                ], true)) {
                    continue;
                }

                $statusAtEnum = OgTicketStatus::from($statusAtValue);

                $tickets[] = [
                    'id' => $ticketId,
                    'subject' => $ticket->subject,
                    'project' => $ticket->project ? [
                        'id' => (int) $ticket->project->id,
                        'name' => $ticket->project->name,
                    ] : null,
                    'priority' => $this->priorityPayload($ticket->priority),
                    'assigned_at' => Carbon::parse($row->assigned_at)->toIso8601String(),
                    'status_at_slot' => ['value' => $statusAtEnum->value, 'label' => $statusAtEnum->label()],
                    'status_now' => ['value' => $ticket->status->value, 'label' => $ticket->status->label()],
                    'is_status_changed' => $statusAtEnum !== $ticket->status,
                ];
            }
        }

        return [
            'account' => $this->accountSummary($account),
            'date' => $date,
            'shift' => $this->shiftSummary($shift),
            'shift_window' => [
                'start' => $shiftStart->toIso8601String(),
                'end' => $shiftEnd->toIso8601String(),
            ],
            'external' => $externalRows->values()->map(function (WorkSchedule $ws): array {
                $ws->loadMissing('project');

                return [
                    'id' => (int) $ws->id,
                    'project' => $ws->project ? [
                        'id' => (int) $ws->project->id,
                        'code' => $ws->project->code,
                        'name' => $ws->project->name,
                    ] : null,
                    'note' => $ws->note,
                    'external_ref' => $ws->external_ref,
                    'source' => 'live',
                ];
            })->all(),
            'tickets' => array_map(static fn (array $t): array => $t + ['source' => 'live'], $tickets),
            'orders' => [],
            'data_source' => 'live',
            'captured_start_at' => null,
            'finalized_at' => null,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function monthRange(string $month): array
    {
        $from = Carbon::parse($month.'-01')->startOfDay();
        $to = $from->copy()->endOfMonth();

        return [$from, $to];
    }

    /**
     * @return list<array{date: string, weekday: int, is_weekend: bool, is_today: bool}>
     */
    protected function buildDays(Carbon $from, Carbon $to): array
    {
        $days = [];
        $today = Carbon::now()->format('Y-m-d');
        $cursor = $from->copy();

        while ($cursor->lessThanOrEqualTo($to)) {
            $weekday = (int) $cursor->dayOfWeekIso;
            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'weekday' => $weekday,
                'is_weekend' => $weekday >= 6,
                'is_today' => $cursor->format('Y-m-d') === $today,
            ];
            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return array{id: int, employee_code: string|null, name: string|null}
     */
    protected function accountSummary(Account $account): array
    {
        return [
            'id' => (int) $account->id,
            'employee_code' => $account->employee_code,
            'name' => $account->name,
        ];
    }

    /**
     * @return array{id: int, project_id: int, code: string, name: string, start_time: string, end_time: string, is_overnight: bool, sort_order: int}
     */
    protected function shiftSummary(Shift $shift): array
    {
        return [
            'id' => (int) $shift->id,
            'project_id' => (int) $shift->project_id,
            'code' => (string) $shift->code,
            'name' => (string) $shift->name,
            'start_time' => $this->formatTime((string) $shift->start_time),
            'end_time' => $this->formatTime((string) $shift->end_time),
            'is_overnight' => $shift->isOvernight(),
            'sort_order' => (int) $shift->sort_order,
        ];
    }

    /**
     * @return array{id: int, code: string|null, name: string}|null
     */
    protected function projectSummary(mixed $project): ?array
    {
        if ($project === null) {
            return null;
        }

        return [
            'id' => (int) $project->id,
            'code' => $project->code ?? null,
            'name' => (string) $project->name,
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    protected function priorityPayload(OgTicketPriority $priority): array
    {
        return ['value' => $priority->value, 'label' => $priority->label()];
    }

    protected function formatTime(string $value): string
    {
        if ($value === '') {
            return '00:00';
        }
        $tail = substr($value, -8);
        $time = strlen($tail) === 8 ? $tail : $value;

        return substr($time, 0, 5);
    }
}
