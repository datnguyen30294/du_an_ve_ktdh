<?php

namespace App\Modules\Platform\Ticket\Repositories;

use App\Common\Models\BaseModel;
use App\Common\Repositories\BaseRepository;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;

class TicketRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Ticket);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    public function findByCode(string $code): ?Ticket
    {
        /** @var Ticket|null */
        return $this->newQuery()->where('code', $code)->first();
    }

    /**
     * Find tickets claimed from pool that exceeded the stale timeout.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Ticket>
     */
    public function findStaleClaimedTickets(int $timeoutMinutes): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Ticket> */
        return $this->newQuery()
            ->whereNotNull('claimed_by_org_id')
            ->where('status', \App\Modules\Platform\Ticket\Enums\TicketStatus::Received->value)
            ->where('is_from_pool', true)
            ->where('claimed_at', '<', now()->subMinutes($timeoutMinutes))
            ->get();
    }

    /**
     * Generate next ticket code for the given year.
     */
    public function generateCode(int $year): string
    {
        $lastTicket = $this->newQuery()
            ->where('code', BaseModel::likeOperator(), "TK-{$year}-%")
            ->orderByDesc('code')
            ->first();

        $sequence = 1;
        if ($lastTicket) {
            $parts = explode('-', $lastTicket->code);
            $sequence = (int) end($parts) + 1;
        }

        return sprintf('TK-%d-%03d', $year, $sequence);
    }
}
