<?php

namespace App\Modules\PMC\Order\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Illuminate\Database\Eloquent\Collection;

class OrderLineRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OrderLine);
    }

    /**
     * Find an order line by id, scoped to a specific order.
     */
    public function findInOrder(int $orderId, int $lineId): ?OrderLine
    {
        /** @var OrderLine|null */
        return $this->newQuery()
            ->where('id', $lineId)
            ->where('order_id', $orderId)
            ->first();
    }

    /**
     * List material lines that have an advance payer assigned.
     * Eager-loads related order, account, and project (via og_ticket).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, OrderLine>
     */
    public function listAdvanceCandidates(array $filters = []): Collection
    {
        $query = $this->newQuery()
            ->where('line_type', QuoteLineType::Material->value)
            ->whereNotNull('advance_payer_id')
            ->whereNotNull('purchase_price')
            ->with([
                'advancePayer:id,name,employee_code,bank_bin,bank_label,bank_account_number,bank_account_name',
                'order:id,code,quote_id',
                'order.quote:id,og_ticket_id',
                'order.quote.ogTicket:id,project_id,subject',
            ]);

        if (! empty($filters['account_id'])) {
            $query->where('advance_payer_id', $filters['account_id']);
        }

        if (! empty($filters['project_id'])) {
            $projectId = (int) $filters['project_id'];
            $query->whereHas('order.quote.ogTicket', fn ($q) => $q->where('project_id', $projectId));
        }

        if (! empty($filters['search'])) {
            $keyword = $filters['search'];
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhereHas('advancePayer', fn ($q2) => $q2
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('employee_code', 'like', "%{$keyword}%"))
                    ->orWhereHas('order', fn ($q2) => $q2->where('code', 'like', "%{$keyword}%"));
            });
        }

        /** @var Collection<int, OrderLine> */
        return $query->orderByDesc('id')->get();
    }

    /**
     * @param  array<int>  $lineIds
     * @return Collection<int, OrderLine>
     */
    public function findManyByIds(array $lineIds): Collection
    {
        /** @var Collection<int, OrderLine> */
        return $this->newQuery()
            ->whereIn('id', $lineIds)
            ->with('advancePayer:id,name')
            ->get();
    }

    /**
     * Sum line_amount for all lines of an order.
     */
    public function sumOrderTotal(int $orderId): float
    {
        return (float) $this->newQuery()
            ->where('order_id', $orderId)
            ->sum('line_amount');
    }
}
