<?php

namespace App\Modules\PMC\Report\Csat\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CsatReportRepository extends BaseRepository
{
    public const DEFAULT_PERIOD_DAYS = 90;

    public const MAX_SCORE = 5;

    public function __construct()
    {
        parent::__construct(new OgTicket);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getDateRange(array $filters): array
    {
        $dateTo = ! empty($filters['date_to'])
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : Carbon::now()->endOfDay();
        $dateFrom = ! empty($filters['date_from'])
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : $dateTo->copy()->subDays(self::DEFAULT_PERIOD_DAYS - 1)->startOfDay();

        return [$dateFrom, $dateTo];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPeriodLabel(array $filters): string
    {
        if (empty($filters['date_from']) && empty($filters['date_to'])) {
            return self::DEFAULT_PERIOD_DAYS.' ngày gần nhất';
        }

        [$dateFrom, $dateTo] = $this->getDateRange($filters);

        return $dateFrom->format('d/m/Y').' - '.$dateTo->format('d/m/Y');
    }

    /**
     * Base query: completed tickets with derived completed_at and project_name.
     *
     * Columns returned:
     * - id, project_id, project_name
     * - resident_rating (nullable)
     * - completed_at (COALESCE(MIN lifecycle completed, og_tickets.updated_at))
     * - has_warranty_request (1 if ticket has any warranty request, else 0)
     *
     * @param  array<string, mixed>  $filters
     */
    private function completedTicketsQuery(array $filters): Builder
    {
        [$dateFrom, $dateTo] = $this->getDateRange($filters);

        $completionTime = DB::table('og_ticket_lifecycle_segments')
            ->selectRaw('og_ticket_id, MIN(started_at) as completed_at')
            ->where('status', OgTicketStatus::Completed->value)
            ->groupBy('og_ticket_id');

        $warrantyFlag = DB::table('og_ticket_warranty_requests')
            ->selectRaw('og_ticket_id, 1 as has_warranty_request')
            ->groupBy('og_ticket_id');

        return DB::table('og_tickets')
            ->select([
                'og_tickets.id',
                'og_tickets.project_id',
                'og_tickets.resident_rating',
                'projects.name as project_name',
                DB::raw('COALESCE(ct.completed_at, og_tickets.updated_at) as completed_at'),
                DB::raw('COALESCE(wr.has_warranty_request, 0) as has_warranty_request'),
            ])
            ->leftJoin('projects', 'og_tickets.project_id', '=', 'projects.id')
            ->leftJoinSub($completionTime, 'ct', 'og_tickets.id', '=', 'ct.og_ticket_id')
            ->leftJoinSub($warrantyFlag, 'wr', 'og_tickets.id', '=', 'wr.og_ticket_id')
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
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getCompletedTickets(array $filters): Collection
    {
        return $this->completedTicketsQuery($filters)->get();
    }
}
