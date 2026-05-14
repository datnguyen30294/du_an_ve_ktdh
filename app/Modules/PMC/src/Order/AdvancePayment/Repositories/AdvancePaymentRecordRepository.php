<?php

namespace App\Modules\PMC\Order\AdvancePayment\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AdvancePaymentRecordRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new AdvancePaymentRecord);
    }

    /**
     * True when there is at least one non-deleted payment record for a given order line.
     */
    public function existsForLine(int $orderLineId): bool
    {
        return $this->newQuery()->where('order_line_id', $orderLineId)->exists();
    }

    /**
     * Get all line IDs that already have a payment record.
     *
     * @param  array<int>  $lineIds
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function pluckPaidLineIds(array $lineIds): \Illuminate\Support\Collection
    {
        return $this->newQuery()
            ->whereIn('order_line_id', $lineIds)
            ->pluck('order_line_id');
    }

    /**
     * Paid-at lookup for a set of line IDs (line_id => paid_at date).
     *
     * @param  array<int>  $lineIds
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function paidAtByLine(array $lineIds): \Illuminate\Support\Collection
    {
        return $this->newQuery()
            ->whereIn('order_line_id', $lineIds)
            ->get(['order_line_id', 'paid_at'])
            ->mapWithKeys(fn ($r) => [$r->order_line_id => $r->paid_at?->toDateString() ?? '']);
    }

    /**
     * Paginated payment history with eager loading.
     *
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['account:id,name,employee_code', 'order:id,code', 'orderLine:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('paid_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('paid_at', '<=', $filters['to']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * Sum of paid amount across all non-deleted records.
     */
    public function sumAllAmount(): float
    {
        return (float) $this->newQuery()->sum('amount');
    }

    /**
     * @param  array<int>  $lineIds
     * @return Collection<int, AdvancePaymentRecord>
     */
    public function findByLineIds(array $lineIds): Collection
    {
        /** @var Collection<int, AdvancePaymentRecord> */
        return $this->newQuery()->whereIn('order_line_id', $lineIds)->get();
    }
}
