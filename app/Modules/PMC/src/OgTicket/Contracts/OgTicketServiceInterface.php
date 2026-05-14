<?php

namespace App\Modules\PMC\OgTicket\Contracts;

use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Pagination\LengthAwarePaginator;

interface OgTicketServiceInterface
{
    /**
     * Get available tickets from pool (via Requester/Ticket ExternalService).
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPool(array $filters): LengthAwarePaginator;

    /**
     * Claim a ticket from pool — creates og_ticket with snapshot data.
     *
     * @param  array<string, mixed>  $data
     */
    public function claim(array $data): OgTicket;

    /**
     * Admin manual-create an og_ticket: creates a central Ticket (is_from_pool=false,
     * claimed_by_org_id=current tenant) and the tenant OgTicket in one flow.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): OgTicket;

    /**
     * List og_tickets for the current tenant.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): OgTicket;

    /**
     * Update processing fields (status, priority, assigned_to, sla, note).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): OgTicket;

    /**
     * Cancel og_ticket and release ticket back to pool.
     *
     * @param  array<string, mixed>  $data
     */
    public function release(int $id, array $data): OgTicket;

    /**
     * Cancel an og_ticket, deactivate draft quote and cancel its order.
     */
    public function delete(int $id): void;

    /**
     * Manually transition og_ticket status (forward or backtrack).
     *
     * @param  array<string, mixed>  $data
     */
    public function manualTransition(int $id, array $data): OgTicket;

    /**
     * Check if an og_ticket can be deleted.
     *
     * @return array{can_delete: bool, message: string}
     */
    public function checkDelete(int $id): array;

    /**
     * Get audit history for an og_ticket.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array;

    /**
     * Sync og_ticket_categories links for an og_ticket.
     *
     * @param  array<int, int>  $categoryIds
     */
    public function syncCategories(int $id, array $categoryIds): OgTicket;
}
