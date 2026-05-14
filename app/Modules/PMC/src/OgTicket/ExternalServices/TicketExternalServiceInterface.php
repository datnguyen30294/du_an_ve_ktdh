<?php

namespace App\Modules\PMC\OgTicket\ExternalServices;

use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TicketExternalServiceInterface
{
    /**
     * Get available tickets from pool.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getAvailableTickets(array $filters): LengthAwarePaginator;

    /**
     * Claim a ticket atomically using DB lock.
     * Returns snapshot data (read inside lock) on success, null if ticket not found,
     * false if already claimed or wrong status.
     *
     * @return array{id: int, requester_name: string, requester_phone: string, apartment_name: string|null, project_id: int, subject: string, description: string|null, address: string|null, latitude: string|null, longitude: string|null, channel: string}|false|null
     */
    public function claimTicket(int $ticketId, string $orgId): array|false|null;

    /**
     * Create a central Ticket on behalf of the given org (admin manual-create flow).
     * Resolves/creates Platform customer by phone. Ticket is created with
     * status=received, is_from_pool=false, claimed_by_org_id=$orgId, claimed_at=now.
     *
     * @param  array{
     *     requester_name: string,
     *     requester_phone: string,
     *     subject: string,
     *     description?: ?string,
     *     address?: ?string,
     *     apartment_name?: ?string,
     *     latitude?: float|string|null,
     *     longitude?: float|string|null,
     *     channel: string,
     *     project_id?: ?int,
     * }  $data
     */
    public function createTicketForOrg(array $data, string $orgId): Ticket;

    /**
     * Delete a central Ticket by ID (used to rollback when og_ticket creation fails
     * after the central Ticket was already created).
     */
    public function deleteTicket(int $ticketId): void;

    /**
     * Sync ticket original status when og_ticket progresses.
     */
    public function updateTicketStatus(int $ticketId, string $status): void;

    /**
     * Sync platform ticket status based on og_ticket new status.
     * Maps OgTicketStatus → TicketStatus and updates the platform ticket.
     */
    public function syncTicketStatus(int $ticketId, OgTicketStatus $newStatus): void;

    /**
     * Release ticket back to pool (status=pending, clear claimed fields).
     */
    public function releaseTicket(int $ticketId): void;

    /**
     * Get ticket codes indexed by platform ticket ID.
     *
     * @param  array<int>  $ticketIds
     * @return Collection<int|string, string>
     */
    public function getTicketCodes(array $ticketIds): Collection;

    /**
     * Fetch the minimum ticket + customer data required to build notification
     * payloads for residents. Returns null when the ticket or its customer
     * cannot be resolved.
     *
     * @return array{customer_id: int, customer_name: string, ticket_code: string, ticket_subject: string}|null
     */
    public function getNotificationInfo(int $ticketId): ?array;
}
