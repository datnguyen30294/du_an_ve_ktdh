<?php

namespace App\Modules\PMC\Report\RevenueTicket\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueTicketReportRepository extends BaseRepository
{
    public const UNCATEGORIZED_LABEL = 'Chưa phân loại';

    public const UNASSIGNED_STAFF_LABEL = 'Chưa gán';

    public const UNASSIGNED_PROJECT_LABEL = 'Chưa gán dự án';

    /** @var list<string> */
    private const RECEIVABLE_REVENUE_STATUSES = [
        'paid',
        'overpaid',
        'completed',
    ];

    public function __construct()
    {
        parent::__construct(new OgTicket);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        $hasFrom = ! empty($filters['date_from']);
        $hasTo = ! empty($filters['date_to']);

        if (! $hasFrom && ! $hasTo) {
            return 'Toàn thời gian';
        }

        $from = $hasFrom ? Carbon::parse((string) $filters['date_from'])->format('d/m/Y') : null;
        $to = $hasTo ? Carbon::parse((string) $filters['date_to'])->format('d/m/Y') : null;

        if ($hasFrom && $hasTo) {
            return "{$from} - {$to}";
        }

        if ($hasFrom) {
            return "Từ {$from}";
        }

        return "Đến {$to}";
    }

    /**
     * Build expanded dataset (one row per ticket × category).
     *
     * Tickets without any category produce exactly one row with category_id=null,
     * category_label='Chưa phân loại'. Tickets with N categories produce N rows.
     *
     * Row object fields:
     *  - ticket_id (int)
     *  - revenue (string decimal)
     *  - completed_date (string Y-m-d)
     *  - project_id (int|null) — resolved
     *  - project_name (string) — resolved with fallbacks
     *  - owner_account_id (int|null) — resolved (first assignee, then received_by_id)
     *  - owner_name (string)
     *  - category_id (int|null)
     *  - category_label (string)
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getDataset(array $filters): Collection
    {
        $base = $this->buildBaseQuery($filters)->get();

        if ($base->isEmpty()) {
            return collect();
        }

        $ticketIds = $base->pluck('ticket_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();

        $assigneeMap = $this->buildFirstAssigneeMap($ticketIds);
        $accountIds = $this->collectAccountIds($base, $assigneeMap);
        $accountNameMap = $this->buildAccountNameMap($accountIds);

        /** @var Collection<int, object> $expanded */
        $expanded = $base->map(function (object $row) use ($assigneeMap, $accountNameMap): object {
            $resolvedProjectId = $row->ticket_project_id !== null
                ? (int) $row->ticket_project_id
                : ($row->receivable_project_id !== null ? (int) $row->receivable_project_id : null);
            $resolvedProjectName = $row->ticket_project_name
                ?? $row->receivable_project_name
                ?? self::UNASSIGNED_PROJECT_LABEL;

            $ticketId = (int) $row->ticket_id;
            $ownerAccountId = $assigneeMap[$ticketId]
                ?? ($row->received_by_id !== null ? (int) $row->received_by_id : null);
            $ownerName = $ownerAccountId !== null
                ? ($accountNameMap[$ownerAccountId] ?? self::UNASSIGNED_STAFF_LABEL)
                : self::UNASSIGNED_STAFF_LABEL;

            $categoryId = $row->category_id !== null ? (int) $row->category_id : null;
            $categoryLabel = $row->category_id !== null
                ? (string) $row->category_name
                : self::UNCATEGORIZED_LABEL;

            return (object) [
                'ticket_id' => $ticketId,
                'receivable_id' => (int) $row->receivable_id,
                'revenue' => (string) $row->revenue,
                'completed_date' => $this->normalizeDate((string) $row->completed_date),
                'project_id' => $resolvedProjectId,
                'project_name' => $resolvedProjectName,
                'owner_account_id' => $ownerAccountId,
                'owner_name' => $ownerName,
                'category_id' => $categoryId,
                'category_label' => $categoryLabel,
            ];
        });

        return $expanded->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildBaseQuery(array $filters): Builder
    {
        $query = DB::table('og_tickets')
            ->select([
                'og_tickets.id as ticket_id',
                'og_tickets.received_by_id',
                'og_tickets.project_id as ticket_project_id',
                'ticket_project.name as ticket_project_name',
                'receivables.id as receivable_id',
                'receivables.project_id as receivable_project_id',
                'receivable_project.name as receivable_project_name',
                'receivables.amount as revenue',
                DB::raw('DATE(orders.completed_at) as completed_date'),
                'og_ticket_categories.id as category_id',
                'og_ticket_categories.name as category_name',
            ])
            ->join('quotes', function ($join): void {
                $join->on('quotes.og_ticket_id', '=', 'og_tickets.id')
                    ->whereNull('quotes.deleted_at');
            })
            ->join('orders', function ($join): void {
                $join->on('orders.quote_id', '=', 'quotes.id')
                    ->whereNull('orders.deleted_at')
                    ->where('orders.status', '=', OrderStatus::Completed->value)
                    ->whereNotNull('orders.completed_at');
            })
            ->join('receivables', function ($join): void {
                $join->on('receivables.order_id', '=', 'orders.id')
                    ->whereNull('receivables.deleted_at')
                    ->whereIn('receivables.status', self::RECEIVABLE_REVENUE_STATUSES);
            })
            ->leftJoin('projects as ticket_project', function ($join): void {
                $join->on('ticket_project.id', '=', 'og_tickets.project_id')
                    ->whereNull('ticket_project.deleted_at');
            })
            ->leftJoin('projects as receivable_project', function ($join): void {
                $join->on('receivable_project.id', '=', 'receivables.project_id')
                    ->whereNull('receivable_project.deleted_at');
            })
            ->leftJoin('og_ticket_category_links', 'og_ticket_category_links.og_ticket_id', '=', 'og_tickets.id')
            ->leftJoin('og_ticket_categories', function ($join): void {
                $join->on('og_ticket_categories.id', '=', 'og_ticket_category_links.og_ticket_category_id')
                    ->whereNull('og_ticket_categories.deleted_at');
            })
            ->whereNull('og_tickets.deleted_at');

        if (! empty($filters['date_from'])) {
            $query->whereDate('orders.completed_at', '>=', (string) $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('orders.completed_at', '<=', (string) $filters['date_to']);
        }

        if (! empty($filters['project_id'])) {
            $projectId = (int) $filters['project_id'];
            $query->where(function ($q) use ($projectId): void {
                $q->where('og_tickets.project_id', $projectId)
                    ->orWhere(function ($q2) use ($projectId): void {
                        $q2->whereNull('og_tickets.project_id')
                            ->where('receivables.project_id', $projectId);
                    });
            });
        }

        return $query;
    }

    /**
     * Resolve first assignee for each ticket.
     * Priority: og_ticket_assignees ordered by created_at ASC, account_id ASC.
     *
     * @param  list<int>  $ticketIds
     * @return array<int, int> map of ticket_id => account_id
     */
    private function buildFirstAssigneeMap(array $ticketIds): array
    {
        if (empty($ticketIds)) {
            return [];
        }

        $rows = DB::table('og_ticket_assignees')
            ->whereIn('og_ticket_id', $ticketIds)
            ->orderBy('og_ticket_id')
            ->orderBy('created_at')
            ->orderBy('account_id')
            ->select(['og_ticket_id', 'account_id'])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $ticketId = (int) $row->og_ticket_id;
            if (! isset($map[$ticketId])) {
                $map[$ticketId] = (int) $row->account_id;
            }
        }

        return $map;
    }

    /**
     * @param  Collection<int, object>  $base
     * @param  array<int, int>  $assigneeMap
     * @return list<int>
     */
    private function collectAccountIds(Collection $base, array $assigneeMap): array
    {
        $ids = array_values($assigneeMap);

        foreach ($base as $row) {
            if ($row->received_by_id !== null) {
                $ids[] = (int) $row->received_by_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $accountIds
     * @return array<int, string>
     */
    private function buildAccountNameMap(array $accountIds): array
    {
        if (empty($accountIds)) {
            return [];
        }

        return DB::table('accounts')
            ->whereIn('id', $accountIds)
            ->pluck('name', 'id')
            ->map(fn ($name): string => (string) $name)
            ->all();
    }

    private function normalizeDate(string $rawDate): string
    {
        return Carbon::parse($rawDate)->format('Y-m-d');
    }
}
