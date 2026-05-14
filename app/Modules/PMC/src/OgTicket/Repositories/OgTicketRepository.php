<?php

namespace App\Modules\PMC\OgTicket\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OgTicketRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OgTicket);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with([
                'receivedBy',
                'assignees',
                'customer:id,code,full_name,phone',
                'ticket:id,code',
                'project:id,name',
                'categories:id,name,sort_order',
                'activeQuote:id,og_ticket_id,is_active',
                'activeQuote.order:id,quote_id',
                'activeQuote.order.receivable:id,order_id,status',
                'activeQuote.order.closingPeriodOrder:id,order_id,closing_period_id',
                'activeQuote.order.closingPeriodOrder.closingPeriod:id,status',
            ])
            ->withCount('warrantyRequests');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(OgTicketStatus::from($filters['status']));
        } else {
            $query->active();
        }

        if (! empty($filters['priority'])) {
            $query->byPriority(OgTicketPriority::from($filters['priority']));
        }

        if (! empty($filters['assignee_id'])) {
            $query->whereHas('assignees', fn ($q) => $q->where('accounts.id', $filters['assignee_id']));
        }

        if (! empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->whereHas('categories', fn ($q) => $q->whereIn('og_ticket_categories.id', $filters['category_ids']));
        }

        if (array_key_exists('has_warranty_request', $filters) && $filters['has_warranty_request'] !== null) {
            $query->hasWarrantyRequest((bool) $filters['has_warranty_request']);
        }

        $this->applySorting($query, $filters, 'created_at');

        return $query->paginate($this->getPerPage($filters));
    }

    public function findLatestByTicketId(int $ticketId): ?OgTicket
    {
        /** @var OgTicket|null */
        return $this->newQuery()
            ->where('ticket_id', $ticketId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Fetch multiple OgTickets by IDs with their project relation, keyed by id.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, OgTicket>
     */
    public function findManyWithProject(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        if ($ids === []) {
            /** @var \Illuminate\Database\Eloquent\Collection<int, OgTicket> */
            return new \Illuminate\Database\Eloquent\Collection;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, OgTicket> */
        return $this->newQuery()
            ->with(['project:id,name'])
            ->whereIn('id', $ids)
            ->get();
    }

    /**
     * Aggregate assignment rows (account_id × status × resident_rating) for a given set of accounts.
     * Returns one row per (og_ticket, assignee) pair — used by the Workforce Capacity screen.
     * Excludes soft-deleted tickets and tickets in `rejected` / `cancelled`.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, object{account_id: int, status: string, resident_rating: int|null}>
     */
    public function aggregateAssignmentsForAccounts(array $accountIds): Collection
    {
        if ($accountIds === []) {
            /** @var Collection<int, object{account_id: int, status: string, resident_rating: int|null}> */
            return new Collection;
        }

        /** @var Collection<int, object{account_id: int, status: string, resident_rating: int|null}> */
        return DB::table('og_tickets as t')
            ->join('og_ticket_assignees as a', 'a.og_ticket_id', '=', 't.id')
            ->whereIn('a.account_id', $accountIds)
            ->whereNotIn('t.status', [
                OgTicketStatus::Rejected->value,
                OgTicketStatus::Cancelled->value,
            ])
            ->whereNull('t.deleted_at')
            ->select([
                'a.account_id',
                't.status',
                't.resident_rating',
            ])
            ->get();
    }

    /**
     * Tickets whose assignment overlaps the given range for the given accounts.
     * One row per (ticket, assignee) pair — the same ticket with N assignees yields N rows.
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, object{ticket_id: int, project_id: int|null, status: string, completed_at: string|null, account_id: int, assigned_at: string, subject: string}>
     */
    public function activeForAccountsInRange(array $accountIds, string $from, string $to): Collection
    {
        if ($accountIds === []) {
            /** @var Collection<int, object{ticket_id: int, project_id: int|null, status: string, completed_at: string|null, account_id: int, assigned_at: string, subject: string}> */
            return new Collection;
        }

        /** @var Collection<int, object{ticket_id: int, project_id: int|null, status: string, completed_at: string|null, account_id: int, assigned_at: string, subject: string}> */
        return DB::table('og_tickets as t')
            ->join('og_ticket_assignees as a', 'a.og_ticket_id', '=', 't.id')
            ->whereIn('a.account_id', $accountIds)
            ->whereNotIn('t.status', [
                OgTicketStatus::Rejected->value,
                OgTicketStatus::Cancelled->value,
            ])
            ->whereNull('t.deleted_at')
            ->where('a.created_at', '<=', $to)
            ->where(function ($q) use ($from): void {
                $q->whereNull('t.completed_at')
                    ->orWhere('t.completed_at', '>=', $from);
            })
            ->select([
                't.id as ticket_id',
                't.project_id',
                't.subject',
                't.status',
                't.completed_at',
                'a.account_id',
                'a.created_at as assigned_at',
            ])
            ->get();
    }
}
