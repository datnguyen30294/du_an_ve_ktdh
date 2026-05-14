<?php

namespace App\Modules\PMC\Customer\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Common\Support\PhoneNormalizer;
use App\Modules\PMC\Customer\Models\Customer;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Customer);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->withCount('ogTickets as ticket_count')
            ->withAvg(['ogTickets as avg_rating' => fn ($q) => $q->whereNotNull('resident_rating')], 'resident_rating');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $this->applySorting($query, $filters, 'last_contacted_at', 'desc');

        return $query->paginate($this->getPerPage($filters));
    }

    public function findByPhone(string $phone): ?Customer
    {
        $normalized = PhoneNormalizer::normalize($phone);

        if ($normalized === '') {
            return null;
        }

        /** @var Customer|null */
        return $this->newQuery()->where('phone', $normalized)->first();
    }

    public function findOrCreateByPhone(string $phone, string $fullName): Customer
    {
        $existing = $this->findByPhone($phone);

        if ($existing) {
            return $existing;
        }

        /** @var Customer */
        return $this->newQuery()->create([
            'phone' => $phone,      // mutator normalizes
            'full_name' => $fullName,
        ]);
    }

    public function hasTickets(int $customerId): bool
    {
        return OgTicket::query()->where('customer_id', $customerId)->exists();
    }

    public function hasOrders(int $customerId): bool
    {
        return Order::query()
            ->whereHas('quote.ogTicket', fn ($q) => $q->where('customer_id', $customerId))
            ->exists();
    }

    /**
     * @return array{
     *     ticket_count: int,
     *     ticket_by_status: array<string, int>,
     *     avg_rating: float|null,
     *     rating_count: int,
     *     order_count: int,
     *     total_paid: string,
     *     total_outstanding: string,
     * }
     */
    public function getAggregates(int $customerId): array
    {
        $ticketCount = OgTicket::query()->where('customer_id', $customerId)->count();

        /** @var array<string, int> $ticketByStatus */
        $ticketByStatus = OgTicket::query()
            ->where('customer_id', $customerId)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        $ratingCount = OgTicket::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('resident_rating')
            ->count();

        $avgRating = $ratingCount > 0
            ? (float) OgTicket::query()
                ->where('customer_id', $customerId)
                ->whereNotNull('resident_rating')
                ->avg('resident_rating')
            : null;

        $orderCount = Order::query()
            ->whereHas('quote.ogTicket', fn ($q) => $q->where('customer_id', $customerId))
            ->count();

        $totalPaid = number_format(
            (float) PaymentReceipt::query()
                ->whereHas(
                    'receivable.order.quote.ogTicket',
                    fn ($q) => $q->where('customer_id', $customerId)
                )
                ->sum('amount'),
            2,
            '.',
            ''
        );

        // SQLite has no GREATEST(); use CASE WHEN for cross-driver compatibility.
        $totalOutstanding = number_format(
            (float) Receivable::query()
                ->whereHas(
                    'order.quote.ogTicket',
                    fn ($q) => $q->where('customer_id', $customerId)
                )
                ->select(DB::raw('COALESCE(SUM(CASE WHEN amount - paid_amount > 0 THEN amount - paid_amount ELSE 0 END), 0) as total'))
                ->value('total'),
            2,
            '.',
            ''
        );

        return [
            'ticket_count' => (int) $ticketCount,
            'ticket_by_status' => $ticketByStatus,
            'avg_rating' => $avgRating,
            'rating_count' => $ratingCount,
            'order_count' => $orderCount,
            'total_paid' => $totalPaid,
            'total_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listTickets(int $customerId, array $filters): LengthAwarePaginator
    {
        $query = OgTicket::query()
            ->with(['project:id,name'])
            ->where('customer_id', $customerId);

        $this->applySorting($query, $filters, 'received_at', 'desc');

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listOrders(int $customerId, array $filters): LengthAwarePaginator
    {
        $query = Order::query()
            ->with(['quote.ogTicket:id,subject,customer_id', 'receivable'])
            ->whereHas('quote.ogTicket', fn ($q) => $q->where('customer_id', $customerId));

        $this->applySorting($query, $filters, 'created_at', 'desc');

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPayments(int $customerId, array $filters): LengthAwarePaginator
    {
        $query = PaymentReceipt::query()
            ->with(['receivable.order:id,code'])
            ->whereHas(
                'receivable.order.quote.ogTicket',
                fn ($q) => $q->where('customer_id', $customerId)
            );

        $this->applySorting($query, $filters, 'paid_at', 'desc');

        return $query->paginate($this->getPerPage($filters));
    }
}
