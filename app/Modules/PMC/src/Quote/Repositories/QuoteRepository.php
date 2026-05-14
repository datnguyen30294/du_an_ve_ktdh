<?php

namespace App\Modules\PMC\Quote\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Pagination\LengthAwarePaginator;

class QuoteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Quote);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['ogTicket:id,subject'])
            ->withCount('lines');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(QuoteStatus::from($filters['status']));
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['og_ticket_id'])) {
            $query->where('og_ticket_id', $filters['og_ticket_id']);
        }

        $this->applySorting($query, $filters, 'created_at');

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * Find the active quote for an OgTicket.
     */
    public function findActiveByOgTicket(int $ogTicketId): ?Quote
    {
        /** @var Quote|null */
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Find the most recently created active quote for an OgTicket.
     */
    public function findLatestActiveByOgTicket(int $ogTicketId): ?Quote
    {
        /** @var Quote|null */
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->latest('id')
            ->first();
    }

    /**
     * Find the "effective" active quote (ManagerApproved or Approved) for an OgTicket.
     * An effective quote is one that has been approved by management and drives ticket status.
     */
    public function findEffectiveByOgTicket(int $ogTicketId): ?Quote
    {
        /** @var Quote|null */
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->whereIn('status', [QuoteStatus::ManagerApproved->value, QuoteStatus::Approved->value])
            ->first();
    }

    /**
     * Find all active quotes for an OgTicket.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Quote>
     */
    public function findAllActiveByOgTicket(int $ogTicketId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Deactivate all active quotes for an OgTicket.
     */
    public function deactivateByOgTicket(int $ogTicketId): void
    {
        $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Deactivate all active quotes for an OgTicket except a specific quote.
     */
    public function deactivateByOgTicketExcept(int $ogTicketId, int $exceptQuoteId): void
    {
        $this->newQuery()
            ->where('og_ticket_id', $ogTicketId)
            ->where('is_active', true)
            ->where('id', '!=', $exceptQuoteId)
            ->update(['is_active' => false]);
    }

    /**
     * Get all quotes for an OgTicket with lines, active first then by created_at desc.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Quote>
     */
    public function getByOgTicket(int $ogTicketId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->with(['lines', 'order'])
            ->where('og_ticket_id', $ogTicketId)
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get active quotes excluding specific ticket IDs.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $excludeTicketIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Quote>
     */
    public function findActiveExcludingTickets(\Illuminate\Support\Collection $excludeTicketIds): \Illuminate\Database\Eloquent\Collection
    {
        return $this->newQuery()
            ->with('ogTicket:id,subject')
            ->withCount('lines')
            ->where('is_active', true)
            ->whereNotIn('og_ticket_id', $excludeTicketIds)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Generate the next quote code for today.
     */
    public function generateCode(): string
    {
        $today = now()->format('Ymd');
        $prefix = "QT-{$today}-";

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
