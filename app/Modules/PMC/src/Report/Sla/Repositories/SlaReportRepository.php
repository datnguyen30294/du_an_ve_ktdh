<?php

namespace App\Modules\PMC\Report\Sla\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SlaReportRepository extends BaseRepository
{
    public const SLA_TARGET_PERCENT = 90;

    public function __construct()
    {
        parent::__construct(new OgTicket);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(array $filters): array
    {
        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(29)->startOfDay();

        return [$dateFrom, $dateTo];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            return '30 ngày gần nhất';
        }

        $dateFrom = Carbon::parse($filters['date_from'] ?? now()->subDays(29))->format('d/m/Y');
        $dateTo = Carbon::parse($filters['date_to'] ?? now())->format('d/m/Y');

        return "{$dateFrom} - {$dateTo}";
    }

    /**
     * Base query: completed tickets with SLA metrics joined.
     *
     * Columns returned:
     * - id, project_id, ticket_id, received_at, sla_quote_due_at, sla_completion_due_at, updated_at
     * - project_name (from projects table)
     * - phase1_completed_at, phase2_completed_at, approved_at (from lifecycle segments)
     * - resolution_completed_at (COALESCE of segment completed_at or updated_at)
     * - max_cycle (for reopened detection)
     *
     * @param  array<string, mixed>  $filters
     */
    private function completedTicketsQuery(array $filters): Builder
    {
        [$dateFrom, $dateTo] = $this->getDateRange($filters);

        // Phase 1: first time reaching quoted or beyond (cycle = 0)
        $phase1 = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MIN(started_at) as phase1_completed_at')
            ->whereIn('status', [
                OgTicketStatus::Quoted->value,
                OgTicketStatus::Approved->value,
                OgTicketStatus::Ordered->value,
                OgTicketStatus::InProgress->value,
                OgTicketStatus::Completed->value,
            ])
            ->where('cycle', 0)
            ->groupBy('og_ticket_id');

        // Phase 2: first confirmed completion
        $phase2 = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MIN(started_at) as phase2_completed_at')
            ->where('status', OgTicketStatus::Completed->value)
            ->where('cycle_confirmed', true)
            ->groupBy('og_ticket_id');

        // Max cycle for reopened detection
        $maxCycle = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MAX(cycle) as max_cycle')
            ->groupBy('og_ticket_id');

        // Completion time for date filtering (any completed segment)
        $completionTime = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MIN(started_at) as completed_at')
            ->where('status', OgTicketStatus::Completed->value)
            ->groupBy('og_ticket_id');

        // Approved time for phase 2 target hours
        $approvedTime = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MIN(started_at) as approved_at')
            ->where('status', OgTicketStatus::Approved->value)
            ->groupBy('og_ticket_id');

        return DB::table('og_tickets')
            ->select([
                'og_tickets.id',
                'og_tickets.project_id',
                'og_tickets.ticket_id',
                'og_tickets.received_at',
                'og_tickets.sla_quote_due_at',
                'og_tickets.sla_completion_due_at',
                'og_tickets.updated_at',
                'projects.name as project_name',
                DB::raw('p1.phase1_completed_at'),
                DB::raw('p2.phase2_completed_at'),
                DB::raw('COALESCE(ct.completed_at, og_tickets.updated_at) as resolution_completed_at'),
                DB::raw('COALESCE(mc.max_cycle, 0) as max_cycle'),
                DB::raw('ap.approved_at'),
            ])
            ->leftJoin('projects', 'og_tickets.project_id', '=', 'projects.id')
            ->leftJoinSub($phase1, 'p1', 'og_tickets.id', '=', 'p1.og_ticket_id')
            ->leftJoinSub($phase2, 'p2', 'og_tickets.id', '=', 'p2.og_ticket_id')
            ->leftJoinSub($maxCycle, 'mc', 'og_tickets.id', '=', 'mc.og_ticket_id')
            ->leftJoinSub($completionTime, 'ct', 'og_tickets.id', '=', 'ct.og_ticket_id')
            ->leftJoinSub($approvedTime, 'ap', 'og_tickets.id', '=', 'ap.og_ticket_id')
            ->where('og_tickets.status', OgTicketStatus::Completed->value)
            ->whereNull('og_tickets.deleted_at')
            ->where(function (Builder $q) use ($dateFrom, $dateTo): void {
                $q->whereBetween('ct.completed_at', [$dateFrom, $dateTo])
                    ->orWhere(function (Builder $q2) use ($dateFrom, $dateTo): void {
                        $q2->whereNull('ct.completed_at')
                            ->whereBetween('og_tickets.updated_at', [$dateFrom, $dateTo]);
                    });
            })
            ->when(! empty($filters['project_id']), function (Builder $q) use ($filters): void {
                $q->where('og_tickets.project_id', $filters['project_id']);
            });
    }

    /**
     * Get all completed tickets with SLA metrics for aggregation.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getCompletedTickets(array $filters): Collection
    {
        return $this->completedTicketsQuery($filters)->get();
    }

    /**
     * Get assignee data for given ticket IDs.
     *
     * @param  array<int>  $ticketIds
     * @return Collection<int, object>
     */
    public function getAssigneesForTickets(array $ticketIds): Collection
    {
        if (empty($ticketIds)) {
            return collect();
        }

        return DB::table('og_ticket_assignees')
            ->join('accounts', 'og_ticket_assignees.account_id', '=', 'accounts.id')
            ->whereIn('og_ticket_assignees.og_ticket_id', $ticketIds)
            ->select([
                'og_ticket_assignees.og_ticket_id',
                'accounts.id as staff_id',
                'accounts.name as staff_name',
            ])
            ->get();
    }

    /**
     * Get categories for given og_ticket IDs.
     *
     * @param  array<int>  $ogTicketIds
     * @return Collection<int, object>
     */
    public function getCategoriesForTickets(array $ogTicketIds): Collection
    {
        if (empty($ogTicketIds)) {
            return collect();
        }

        return DB::table('og_ticket_category_links')
            ->join('og_ticket_categories', 'og_ticket_category_links.og_ticket_category_id', '=', 'og_ticket_categories.id')
            ->whereIn('og_ticket_category_links.og_ticket_id', $ogTicketIds)
            ->orderBy('og_ticket_categories.sort_order')
            ->orderBy('og_ticket_categories.name')
            ->select([
                'og_ticket_category_links.og_ticket_id',
                'og_ticket_categories.id as category_id',
                'og_ticket_categories.name as category_name',
            ])
            ->get();
    }
}
