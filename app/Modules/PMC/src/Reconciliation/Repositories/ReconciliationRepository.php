<?php

namespace App\Modules\PMC\Reconciliation\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Pagination\LengthAwarePaginator;

class ReconciliationRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new FinancialReconciliation);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with([
                'receivable:id,order_id,project_id,amount,paid_amount,status',
                'receivable.order:id,code,quote_id',
                'receivable.order.quote:id,og_ticket_id',
                'receivable.order.quote.ogTicket:id,requester_name,apartment_name,customer_id',
                'receivable.order.quote.ogTicket.customer:id,code,full_name,phone',
                'receivable.project:id,name',
                'paymentReceipt:id,type,amount,payment_method,collected_by_id,paid_at',
                'paymentReceipt.collectedBy:id,name',
                'sourceCashTransaction:id,code,category,direction,amount,transaction_date,note,created_by_id',
                'sourceCashTransaction.createdBy:id,name',
                'reconciledBy:id,name',
                'cashTransaction:id,code,financial_reconciliation_id',
            ]);

        $this->applyFiltersToQuery($query, $filters);

        $this->applySorting($query, $filters, 'created_at');

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * Get summary statistics with same filters as list.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_count: int, pending_count: int, reconciled_count: int, rejected_count: int, pending_amount: string, reconciled_amount: string, rejected_amount: string}
     */
    public function getSummary(array $filters = []): array
    {
        $baseQuery = $this->newQuery();

        $this->applyFiltersToQuery($baseQuery, $filters);

        $pendingQuery = (clone $baseQuery)->where('financial_reconciliations.status', ReconciliationStatus::Pending->value);
        $reconciledQuery = (clone $baseQuery)->where('financial_reconciliations.status', ReconciliationStatus::Reconciled->value);
        $rejectedQuery = (clone $baseQuery)->where('financial_reconciliations.status', ReconciliationStatus::Rejected->value);

        return [
            'total_count' => (clone $baseQuery)->count('financial_reconciliations.id'),
            'pending_count' => (clone $pendingQuery)->count('financial_reconciliations.id'),
            'reconciled_count' => (clone $reconciledQuery)->count('financial_reconciliations.id'),
            'rejected_count' => (clone $rejectedQuery)->count('financial_reconciliations.id'),
            'pending_amount' => number_format((float) $pendingQuery->sum('financial_reconciliations.amount'), 2, '.', ''),
            'reconciled_amount' => number_format((float) $reconciledQuery->sum('financial_reconciliations.amount'), 2, '.', ''),
            'rejected_amount' => number_format((float) $rejectedQuery->sum('financial_reconciliations.amount'), 2, '.', ''),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, FinancialReconciliation>
     */
    public function findByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()->whereIn('id', $ids)->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinancialReconciliation>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFiltersToQuery($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $keyword = $filters['search'];
            $query->where(function ($q) use ($keyword): void {
                // Receivable source: search by order code / requester / apartment.
                $q->whereHas('receivable', function ($q1) use ($keyword): void {
                    $q1->whereHas('order', function ($q2) use ($keyword): void {
                        $q2->where('code', Receivable::likeOperator(), "%{$keyword}%");
                    })->orWhereHas('order.quote.ogTicket', function ($q2) use ($keyword): void {
                        $q2->where('requester_name', Receivable::likeOperator(), "%{$keyword}%")
                            ->orWhere('apartment_name', Receivable::likeOperator(), "%{$keyword}%");
                    })->orWhereHas('order.quote.ogTicket.customer', function ($q2) use ($keyword): void {
                        $q2->where('full_name', Receivable::likeOperator(), "%{$keyword}%")
                            ->orWhere('phone', Receivable::likeOperator(), "%{$keyword}%");
                    });
                })
                    // Manual source: search by cash tx code or note.
                    ->orWhereHas('sourceCashTransaction', function ($q1) use ($keyword): void {
                        $q1->where('code', Receivable::likeOperator(), "%{$keyword}%")
                            ->orWhere('note', Receivable::likeOperator(), "%{$keyword}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('financial_reconciliations.status', ReconciliationStatus::from($filters['status'])->value);
        }

        if (! empty($filters['source'])) {
            if ($filters['source'] === 'manual_cash') {
                $query->whereNotNull('financial_reconciliations.cash_transaction_id');
            } elseif ($filters['source'] === 'receivable') {
                $query->whereNotNull('financial_reconciliations.payment_receipt_id');
            }
        }

        if (! empty($filters['receivable_id'])) {
            $query->where('financial_reconciliations.receivable_id', (int) $filters['receivable_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->whereHas('receivable', fn ($q) => $q->where('project_id', (int) $filters['project_id']));
        }

        if (! empty($filters['type'])) {
            $query->whereHas('paymentReceipt', fn ($q) => $q->where('type', $filters['type']));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('financial_reconciliations.reconciled_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('financial_reconciliations.reconciled_at', '<=', $filters['date_to']);
        }
    }
}
