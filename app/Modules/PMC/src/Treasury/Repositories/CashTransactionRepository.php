<?php

namespace App\Modules\PMC\Treasury\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CashTransactionRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CashTransaction);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CashTransaction>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQueryWithDeletedFilter($filters)
            ->with([
                'cashAccount:id,code,name,type',
                'order:id,code',
                'financialReconciliation:id,payment_receipt_id,receivable_id,status,reconciled_at',
                'financialReconciliation.paymentReceipt:id,type,amount,paid_at',
                'manualReconciliation:id,cash_transaction_id,status,reconciled_at,reconciled_by_id',
                'manualReconciliation.reconciledBy:id,name',
                'commissionSnapshot:id,order_id,amount,paid_out_at',
                'createdBy:id,name',
                'deletedBy:id,name',
            ]);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters, 'transaction_date', 'desc');

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function findById(int|string $id, array $columns = ['*'], array $relations = []): Model
    {
        if ($relations === []) {
            $relations = [
                'cashAccount:id,code,name,type',
                'order:id,code',
                'financialReconciliation.paymentReceipt.collectedBy:id,name',
                'financialReconciliation.receivable.order:id,code',
                'manualReconciliation:id,cash_transaction_id,status,reconciled_at,reconciled_by_id',
                'manualReconciliation.reconciledBy:id,name',
                'commissionSnapshot.order:id,code',
                'commissionSnapshot.account:id,name',
                'createdBy:id,name',
                'deletedBy:id,name',
            ];
        }

        /** @var CashTransaction */
        return CashTransaction::withTrashed()
            ->select($columns)
            ->with($relations)
            ->findOrFail($id);
    }

    public function findActiveByReconciliationId(int $reconciliationId): ?CashTransaction
    {
        /** @var CashTransaction|null */
        return $this->newQuery()
            ->where('financial_reconciliation_id', $reconciliationId)
            ->first();
    }

    public function findActiveByCommissionSnapshotId(int $snapshotId): ?CashTransaction
    {
        /** @var CashTransaction|null */
        return $this->newQuery()
            ->where('commission_snapshot_id', $snapshotId)
            ->first();
    }

    public function findActiveByAdvancePaymentRecordId(int $recordId): ?CashTransaction
    {
        /** @var CashTransaction|null */
        return $this->newQuery()
            ->where('advance_payment_record_id', $recordId)
            ->first();
    }

    /**
     * Get the highest existing counter for a given prefix/year combination,
     * including soft-deleted rows so codes are never reused.
     */
    public function getLastCodeCounterForYear(string $prefix, int $year): int
    {
        $likePrefix = "{$prefix}-{$year}-";

        $lastCode = CashTransaction::withTrashed()
            ->where('code', 'like', $likePrefix.'%')
            ->orderByDesc('code')
            ->value('code');

        if (! $lastCode) {
            return 0;
        }

        return (int) substr($lastCode, -4);
    }

    /**
     * Compute current balance for a cash account.
     * Balance = opening_balance + sum(inflow) - sum(outflow), excluding soft-deleted tx.
     */
    public function computeBalance(CashAccount $account): float
    {
        $inflow = (float) $this->newQuery()
            ->where('cash_account_id', $account->id)
            ->where('direction', CashTransactionDirection::Inflow->value)
            ->sum('amount');

        $outflow = (float) $this->newQuery()
            ->where('cash_account_id', $account->id)
            ->where('direction', CashTransactionDirection::Outflow->value)
            ->sum('amount');

        return (float) $account->opening_balance + $inflow - $outflow;
    }

    /**
     * Aggregate summary numbers for KPI display.
     *
     * @param  array<string, mixed>  $filters
     * @return array{total_inflow: float, total_outflow: float, transaction_count: int, inflow_by_category: list<array{category: string, amount: float, count: int}>, outflow_by_category: list<array{category: string, amount: float, count: int}>}
     */
    public function getSummary(CashAccount $account, array $filters): array
    {
        $baseQuery = $this->newQuery()
            ->where('cash_account_id', $account->id);

        if (! empty($filters['date_from'])) {
            $baseQuery->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $baseQuery->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        $totalInflow = (float) (clone $baseQuery)->where('direction', CashTransactionDirection::Inflow->value)->sum('amount');
        $totalOutflow = (float) (clone $baseQuery)->where('direction', CashTransactionDirection::Outflow->value)->sum('amount');
        $transactionCount = (clone $baseQuery)->count();

        // Alias the category column so the Eloquent cast for `category` does not
        // interfere — we want the raw enum value for grouping in the response.
        $inflowByCategory = (clone $baseQuery)
            ->where('direction', CashTransactionDirection::Inflow->value)
            ->groupBy('category')
            ->selectRaw('category as category_value, SUM(amount) as total_amount, COUNT(*) as total_count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_value,
                'amount' => (float) $row->total_amount,
                'count' => (int) $row->total_count,
            ])
            ->values()
            ->all();

        $outflowByCategory = (clone $baseQuery)
            ->where('direction', CashTransactionDirection::Outflow->value)
            ->groupBy('category')
            ->selectRaw('category as category_value, SUM(amount) as total_amount, COUNT(*) as total_count')
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->category_value,
                'amount' => (float) $row->total_amount,
                'count' => (int) $row->total_count,
            ])
            ->values()
            ->all();

        return [
            'total_inflow' => $totalInflow,
            'total_outflow' => $totalOutflow,
            'transaction_count' => $transactionCount,
            'inflow_by_category' => $inflowByCategory,
            'outflow_by_category' => $outflowByCategory,
        ];
    }

    /**
     * Base query honouring the include_deleted filter.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<CashTransaction>
     */
    private function newQueryWithDeletedFilter(array $filters): Builder
    {
        $mode = $filters['include_deleted'] ?? 'none';

        return match ($mode) {
            'all' => CashTransaction::withTrashed(),
            'manual' => CashTransaction::withTrashed()
                ->where(function (Builder $q): void {
                    $q->whereNull('deleted_at')
                        ->orWhere('auto_deleted', false);
                }),
            'auto' => CashTransaction::withTrashed()
                ->where(function (Builder $q): void {
                    $q->whereNull('deleted_at')
                        ->orWhere('auto_deleted', true);
                }),
            default => $this->newQuery(),
        };
    }

    /**
     * @param  Builder<CashTransaction>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['cash_account_id'])) {
            $query->where('cash_account_id', (int) $filters['cash_account_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('transaction_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('transaction_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['direction'])) {
            $query->where('direction', CashTransactionDirection::from($filters['direction'])->value);
        }

        if (! empty($filters['category'])) {
            $query->where('category', CashTransactionCategory::from($filters['category'])->value);
        }

        if (! empty($filters['order_id'])) {
            $query->where('order_id', (int) $filters['order_id']);
        }

        if (! empty($filters['search'])) {
            $keyword = $filters['search'];
            $operator = CashTransaction::likeOperator();
            $query->where(function (Builder $q) use ($keyword, $operator): void {
                $q->where('code', $operator, "%{$keyword}%")
                    ->orWhere('note', $operator, "%{$keyword}%");
            });
        }
    }
}
