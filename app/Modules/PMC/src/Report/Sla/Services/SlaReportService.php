<?php

namespace App\Modules\PMC\Report\Sla\Services;

use App\Common\Services\BaseService;
use App\Modules\PMC\OgTicket\ExternalServices\TicketExternalServiceInterface;
use App\Modules\PMC\Report\Sla\Contracts\SlaReportServiceInterface;
use App\Modules\PMC\Report\Sla\Enums\SlaResult;
use App\Modules\PMC\Report\Sla\Repositories\SlaReportRepository;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class SlaReportService extends BaseService implements SlaReportServiceInterface
{
    public function __construct(
        protected SlaReportRepository $repository,
        protected TicketExternalServiceInterface $ticketExternalService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array
    {
        $tickets = $this->repository->getCompletedTickets($filters);
        $total = $tickets->count();

        if ($total === 0) {
            return [
                'period_label' => $this->repository->getPeriodLabel($filters),
                'sla_target_percent' => SlaReportRepository::SLA_TARGET_PERCENT,
                'on_time_rate' => 0,
                'breached_count' => 0,
                'median_resolution_hours' => 0,
                'reopened_rate' => 0,
            ];
        }

        $breachedCount = 0;
        $reopenedCount = 0;
        $resolutionHours = [];

        foreach ($tickets as $ticket) {
            if ($this->isPhase1Breached($ticket) || $this->isPhase2Breached($ticket)) {
                $breachedCount++;
            }

            if ((int) $ticket->max_cycle > 0) {
                $reopenedCount++;
            }

            $resolutionHours[] = $this->computeResolutionHours($ticket);
        }

        return [
            'period_label' => $this->repository->getPeriodLabel($filters),
            'sla_target_percent' => SlaReportRepository::SLA_TARGET_PERCENT,
            'on_time_rate' => round(($total - $breachedCount) / $total * 100, 1),
            'breached_count' => $breachedCount,
            'median_resolution_hours' => $this->computeMedian($resolutionHours),
            'reopened_rate' => round($reopenedCount / $total * 100, 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getTrend(array $filters): array
    {
        $months = (int) ($filters['months'] ?? 6);

        // Override date range for trend if not explicitly set
        $trendFilters = $filters;
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            $trendFilters['date_from'] = Carbon::now()->subMonths($months - 1)->startOfMonth()->format('Y-m-d');
            $trendFilters['date_to'] = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $tickets = $this->repository->getCompletedTickets($trendFilters);

        // Group by completion month
        $grouped = $tickets->groupBy(function ($ticket) {
            return Carbon::parse($ticket->resolution_completed_at)->format('Y-m');
        });

        // Build monthly trend with all months filled
        $startMonth = empty($filters['date_from']) && empty($filters['date_to'])
            ? Carbon::now()->subMonths($months - 1)->startOfMonth()
            : Carbon::parse($trendFilters['date_from'])->startOfMonth();
        $endMonth = empty($filters['date_from']) && empty($filters['date_to'])
            ? Carbon::now()->startOfMonth()
            : Carbon::parse($trendFilters['date_to'])->startOfMonth();

        $result = [];
        $current = $startMonth->copy();

        while ($current->lte($endMonth)) {
            $key = $current->format('Y-m');
            $monthTickets = $grouped->get($key, collect());
            $total = $monthTickets->count();

            $breached = 0;
            foreach ($monthTickets as $ticket) {
                if ($this->isPhase1Breached($ticket) || $this->isPhase2Breached($ticket)) {
                    $breached++;
                }
            }

            $result[] = [
                'month' => 'T'.$current->month,
                'on_time_rate' => $total > 0 ? round(($total - $breached) / $total * 100, 1) : 0,
            ];

            $current->addMonth();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByProject(array $filters): array
    {
        $tickets = $this->repository->getCompletedTickets($filters);

        return $tickets->groupBy('project_id')->map(function ($projectTickets, $projectId) {
            $total = $projectTickets->count();
            $breached = 0;
            $totalHours = 0;

            foreach ($projectTickets as $ticket) {
                if ($this->isPhase1Breached($ticket) || $this->isPhase2Breached($ticket)) {
                    $breached++;
                }
                $totalHours += $this->computeResolutionHours($ticket);
            }

            return [
                'project_id' => (int) $projectId,
                'project_name' => $projectTickets->first()->project_name,
                'tickets_closed' => $total,
                'on_time_rate' => round(($total - $breached) / $total * 100, 1),
                'breached' => $breached,
                'avg_hours' => round($totalHours / $total, 1),
            ];
        })->values()->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByStaff(array $filters): array
    {
        $tickets = $this->repository->getCompletedTickets($filters);

        if ($tickets->isEmpty()) {
            return [];
        }

        $ticketIds = $tickets->pluck('id')->toArray();
        $assignees = $this->repository->getAssigneesForTickets($ticketIds);
        $ticketMap = $tickets->keyBy('id');

        // Expand by assignees and group by (project_id, staff_id)
        /** @var array<string, array{project_id: int, project_name: string, staff_id: int, staff_name: string, tickets: list<object>}> $groups */
        $groups = [];
        foreach ($assignees as $assignment) {
            $ticket = $ticketMap->get($assignment->og_ticket_id);
            if (! $ticket) {
                continue;
            }

            $key = $ticket->project_id.'_'.$assignment->staff_id;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'project_id' => (int) $ticket->project_id,
                    'project_name' => $ticket->project_name,
                    'staff_id' => (int) $assignment->staff_id,
                    'staff_name' => $assignment->staff_name,
                    'tickets' => [],
                ];
            }
            $groups[$key]['tickets'][] = $ticket;
        }

        return collect($groups)->map(function (array $group) {
            $total = count($group['tickets']);
            $breached = 0;
            $totalHours = 0;

            foreach ($group['tickets'] as $ticket) {
                if ($this->isPhase1Breached($ticket) || $this->isPhase2Breached($ticket)) {
                    $breached++;
                }
                $totalHours += $this->computeResolutionHours($ticket);
            }

            return [
                'project_id' => $group['project_id'],
                'project_name' => $group['project_name'],
                'staff_id' => $group['staff_id'],
                'staff_name' => $group['staff_name'],
                'tickets_handled' => $total,
                'on_time_rate' => round(($total - $breached) / $total * 100, 1),
                'breached' => $breached,
                'avg_resolution_hours' => round($totalHours / $total, 1),
            ];
        })->values()->toArray();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getByTicket(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $currentPage = max(1, (int) ($filters['page'] ?? LengthAwarePaginator::resolveCurrentPage()));
        $tickets = $this->repository->getCompletedTickets($filters)
            ->sortByDesc('id')
            ->values();

        $ticketIds = $tickets
            ->pluck('ticket_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $ticketCodes = $this->ticketExternalService->getTicketCodes($ticketIds);

        $ogTicketIds = $tickets
            ->pluck('id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $categoriesByOgTicket = $this->repository
            ->getCategoriesForTickets($ogTicketIds)
            ->groupBy('og_ticket_id')
            ->map(fn ($rows) => $rows->pluck('category_name')->values()->all());

        $expandedRows = [];
        foreach ($tickets as $ticket) {
            $ticketCode = $ticketCodes->get($ticket->ticket_id);
            $categories = $categoriesByOgTicket->get((int) $ticket->id, []);
            $phase1CompletedAt = $this->resolvePhaseCompletedAt(
                $ticket->phase1_completed_at ?? null,
                $ticket->resolution_completed_at ?? null,
                $ticket->sla_quote_due_at ?? null,
            );
            $phase2CompletedAt = $this->resolvePhaseCompletedAt(
                $ticket->phase2_completed_at ?? null,
                $ticket->resolution_completed_at ?? null,
                $ticket->sla_completion_due_at ?? null,
            );

            if (! empty($ticket->sla_quote_due_at)) {
                $expandedRows[] = [
                    'ticket_id' => (int) $ticket->id,
                    'ticket_code' => $ticketCode,
                    'project_name' => $ticket->project_name,
                    'categories' => $categories,
                    'phase' => 'Giai đoạn 1',
                    'sla_target_hours' => $this->computeTargetHours($ticket->received_at, $ticket->sla_quote_due_at),
                    'actual_hours' => $this->computeActualHours($ticket->received_at, $phase1CompletedAt),
                    'result' => $this->buildResultEnum($phase1CompletedAt, $ticket->sla_quote_due_at),
                ];
            }

            if (! empty($ticket->sla_completion_due_at)) {
                $expandedRows[] = [
                    'ticket_id' => (int) $ticket->id,
                    'ticket_code' => $ticketCode,
                    'project_name' => $ticket->project_name,
                    'categories' => $categories,
                    'phase' => 'Giai đoạn 2',
                    'sla_target_hours' => $this->computeTargetHours($ticket->approved_at, $ticket->sla_completion_due_at),
                    'actual_hours' => $this->computeActualHours($ticket->approved_at, $phase2CompletedAt),
                    'result' => $this->buildResultEnum($phase2CompletedAt, $ticket->sla_completion_due_at),
                ];
            }
        }

        $total = count($expandedRows);
        $items = array_slice($expandedRows, ($currentPage - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    private function isPhase1Breached(object $ticket): bool
    {
        $completedAt = $this->resolvePhaseCompletedAt(
            $ticket->phase1_completed_at ?? null,
            $ticket->resolution_completed_at ?? null,
            $ticket->sla_quote_due_at ?? null,
        );

        if (empty($ticket->sla_quote_due_at) || empty($completedAt)) {
            return false;
        }

        return Carbon::parse($completedAt)->gt(Carbon::parse($ticket->sla_quote_due_at));
    }

    private function isPhase2Breached(object $ticket): bool
    {
        $completedAt = $this->resolvePhaseCompletedAt(
            $ticket->phase2_completed_at ?? null,
            $ticket->resolution_completed_at ?? null,
            $ticket->sla_completion_due_at ?? null,
        );

        if (empty($ticket->sla_completion_due_at) || empty($completedAt)) {
            return false;
        }

        return Carbon::parse($completedAt)->gt(Carbon::parse($ticket->sla_completion_due_at));
    }

    private function computeResolutionHours(object $ticket): float
    {
        $receivedAt = Carbon::parse($ticket->received_at);
        $completedAt = Carbon::parse($ticket->resolution_completed_at);

        return $receivedAt->diffInSeconds($completedAt) / 3600;
    }

    /**
     * @param  list<float>  $values
     */
    private function computeMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($values[$mid - 1] + $values[$mid]) / 2, 1);
        }

        return round($values[$mid], 1);
    }

    private function computeTargetHours(?string $start, ?string $due): ?float
    {
        if (empty($start) || empty($due)) {
            return null;
        }

        return round(Carbon::parse($start)->diffInSeconds(Carbon::parse($due)) / 3600, 1);
    }

    private function computeActualHours(?string $start, ?string $end): ?float
    {
        if (empty($start) || empty($end)) {
            return null;
        }

        $seconds = Carbon::parse($start)->diffInSeconds(Carbon::parse($end));

        return round(max(0.0, $seconds / 3600), 1);
    }

    private function resolvePhaseCompletedAt(?string $phaseCompletedAt, ?string $resolutionCompletedAt, ?string $dueAt): ?string
    {
        if (! empty($phaseCompletedAt)) {
            return $phaseCompletedAt;
        }

        if (! empty($dueAt) && ! empty($resolutionCompletedAt)) {
            return $resolutionCompletedAt;
        }

        return null;
    }

    /**
     * @return array{value: string, label: string}
     */
    private function buildResultEnum(?string $completedAt, ?string $dueAt): array
    {
        if (empty($completedAt) || empty($dueAt)) {
            $result = SlaResult::OnTime;
        } elseif (Carbon::parse($completedAt)->gt(Carbon::parse($dueAt))) {
            $result = SlaResult::Breached;
        } else {
            $result = SlaResult::OnTime;
        }

        return [
            'value' => $result->value,
            'label' => $result->label(),
        ];
    }
}
