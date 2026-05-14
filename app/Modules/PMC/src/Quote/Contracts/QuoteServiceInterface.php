<?php

namespace App\Modules\PMC\Quote\Contracts;

use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Pagination\LengthAwarePaginator;

interface QuoteServiceInterface
{
    /**
     * List quotes with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Quote;

    /**
     * Check if an OgTicket has an active quote. Returns the active quote with lines, or null.
     */
    public function checkActive(int $ogTicketId): ?Quote;

    /**
     * Create a new quote with lines.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Quote;

    /**
     * Update a draft + active quote (replace lines).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Quote;

    /**
     * Transition quote to a new status. State machine validates the transition.
     *
     * @param  array<string, mixed>  $data  { status: string, note?: string }
     */
    public function transition(int $id, array $data): Quote;

    /**
     * Deactivate a quote (set is_active = false).
     */
    public function delete(int $id): void;

    /**
     * Cancel active quote by OgTicket ID (cascade from OgTicket deletion).
     */
    public function cancelByOgTicket(int $ogTicketId): void;

    /**
     * Check if a quote can be deleted.
     *
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array;

    /**
     * Get all quote versions for an OgTicket (active first, then by created_at desc).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Quote>
     */
    public function getVersionsByTicket(int $ogTicketId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Get audit history for a quote.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array;

    /**
     * Get total fixed commission amount for the project linked to an OgTicket.
     */
    public function getCommissionFixedTotal(int $ogTicketId): float;
}
