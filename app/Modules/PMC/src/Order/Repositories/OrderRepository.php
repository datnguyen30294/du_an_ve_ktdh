<?php

namespace App\Modules\PMC\Order\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Order);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['quote:id,code,og_ticket_id', 'quote.ogTicket:id,subject'])
            ->withCount('lines');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(OrderStatus::from($filters['status']));
        } else {
            $query->active();
        }

        $this->applySorting($query, $filters, 'created_at');

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * Check if a ticket already has a non-cancelled order.
     */
    public function hasActiveOrder(int $ogTicketId): bool
    {
        return $this->newQuery()
            ->whereHas('quote', fn ($q) => $q->where('og_ticket_id', $ogTicketId))
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->exists();
    }

    /**
     * Find the non-cancelled order for a ticket.
     */
    public function findActiveOrder(int $ogTicketId): ?Order
    {
        return $this->newQuery()
            ->whereHas('quote', fn ($q) => $q->where('og_ticket_id', $ogTicketId))
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->with('lines')
            ->first();
    }

    /**
     * Get ticket IDs that have a non-cancelled order.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function getTicketIdsWithActiveOrder(): \Illuminate\Support\Collection
    {
        return $this->newQuery()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereHas('quote')
            ->get()
            ->pluck('quote.og_ticket_id')
            ->filter()
            ->unique();
    }

    /**
     * Find the non-cancelled order for a specific quote.
     */
    public function findByQuoteId(int $quoteId): ?Order
    {
        return $this->newQuery()
            ->where('quote_id', $quoteId)
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->first();
    }

    /**
     * Generate the next order code for today.
     */
    public function generateCode(): string
    {
        $today = now()->format('Ymd');
        $prefix = "SO-{$today}-";

        $lastCode = $this->newQuery()
            ->withTrashed()
            ->where('code', 'like', "{$prefix}%")
            ->orderByDesc('code')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
