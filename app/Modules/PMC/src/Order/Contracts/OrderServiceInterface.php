<?php

namespace App\Modules\PMC\Order\Contracts;

use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderServiceInterface
{
    /**
     * List orders with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Order;

    /**
     * Get available quotes (approved + active + no active order).
     *
     * @return Collection<int, \App\Modules\PMC\Quote\Models\Quote>
     */
    public function availableQuotes(): Collection;

    /**
     * Create an order from an approved quote.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Order;

    /**
     * Update order note.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Order;

    /**
     * Transition order to a new status. State machine validates the transition.
     *
     * @param  array<string, mixed>  $data  { status: string }
     */
    public function transition(int $id, array $data): Order;

    /**
     * Soft-delete a draft order.
     */
    public function delete(int $id): void;

    /**
     * Check if an order can be deleted.
     *
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array;

    /**
     * Cancel order by quote ID (cascade from Quote deletion).
     */
    public function cancelByQuote(int $quoteId): void;

    /**
     * Block if the ticket has a completed order.
     */
    public function ensureOrderNotCompleted(int $ogTicketId): void;

    /**
     * Ensure that the ticket's order (if any) allows quote replacement.
     * Throws only if a completed order exists.
     */
    public function ensureCanReplaceQuote(int $ogTicketId): void;

    /**
     * Re-link an active order to a new active quote.
     * Resets order to draft and re-syncs lines.
     */
    public function relinkToActiveQuote(int $ogTicketId, Quote $newQuote): void;

    /**
     * Find the active (non-cancelled) order for a ticket.
     */
    public function findActiveOrderByTicket(int $ogTicketId): ?Order;

    /**
     * Set or clear the advance payer on a specific order line.
     * Line must belong to $orderId and be of type `material`.
     */
    public function setAdvancePayer(int $orderId, int $lineId, ?int $advancePayerId): Order;

    /**
     * Update unit_price (+ optional purchase_price) on a specific order line.
     * Recalculates line_amount, order total_amount, and syncs receivable.
     *
     * @param  array{unit_price: float|int|string, purchase_price?: float|int|string|null}  $data
     */
    public function updateLinePrices(int $orderId, int $lineId, array $data): Order;

    /**
     * List active accounts (candidates for advance payer selection) with optional search.
     * Results are capped server-side to keep payload small for dropdown usage.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\PMC\Account\Models\Account>
     */
    public function listActiveAccounts(?string $search = null): \Illuminate\Database\Eloquent\Collection;
}
