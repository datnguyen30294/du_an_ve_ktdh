<?php

namespace App\Modules\Platform\Ticket\ExternalServices;

use App\Modules\Platform\Ticket\Models\Ticket;

interface OgTicketExternalServiceInterface
{
    /**
     * Create an OgTicket from a Ticket that was auto-claimed at submission.
     */
    public function createFromTicket(Ticket $ticket): void;

    /**
     * Get PMC processing info for a ticket (visible to requester).
     *
     * @return array{
     *     status: array{value: string, label: string},
     *     priority: array{value: string, label: string},
     *     received_at: string|null,
     *     received_by: array{id: int, name: string}|null,
     *     assignees: list<array{id: int, name: string}>,
     *     sla_due_at: string|null,
     * }|null
     */
    public function getProcessingInfoByTicketId(int $ticketId): ?array;

    /**
     * Check if the OgTicket has had a status change within the timeout period.
     *
     * @return bool true if status changed recently (not stale), false if stale or not found
     */
    public function hasRecentStatusChange(int $ticketId, string $orgId, int $timeoutMinutes): bool;

    /**
     * Auto-release a stale OgTicket back to pool.
     * Uses pessimistic lock + audit re-check to prevent race conditions.
     * Cancels OgTicket in tenant DB and resets central Ticket to pending.
     *
     * @return bool true if released, false if not stale or already completed/cancelled
     */
    public function autoReleaseOgTicket(int $ticketId, string $orgId, int $timeoutMinutes): bool;

    /**
     * Sync resident rating from platform ticket to tenant og_ticket.
     */
    public function syncRating(Ticket $ticket): void;

    /**
     * Get order summary for public ticket rating page.
     *
     * @return array{code: string, status: array{value: string, label: string}, total_amount: string, lines: list<array{name: string, quantity: int, unit: string, unit_price: string, line_amount: string, line_type: array{value: string, label: string}}>}|null
     */
    public function getOrderInfoByTicketId(int $ticketId): ?array;

    /**
     * Get active quote info for public ticket page.
     * Returns quote data when quote exists (regardless of whether order exists yet).
     * Only returns data for statuses visible to resident: manager_approved, approved, resident_rejected.
     *
     * @return array{code: string, status: array{value: string, label: string}, total_amount: string, lines: list<array{name: string, quantity: int, unit: string, unit_price: string, line_amount: string, line_type: array{value: string, label: string}}>, is_resident_actionable: bool, manager_approved_at: string|null, note: string|null}|null
     */
    public function getQuoteInfoByTicketId(int $ticketId): ?array;

    /**
     * Apply resident's approve/reject decision on active quote.
     *
     * @throws \App\Common\Exceptions\BusinessException
     */
    public function decideQuoteByTicketId(int $ticketId, string $action, ?string $reason): void;

    /**
     * Submit a warranty request from the resident, stored directly in the tenant DB.
     * Validates that the ticket has a completed order still within the 12-month warranty window.
     *
     * @param  array{subject: string, description: string}  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     *
     * @throws \App\Common\Exceptions\BusinessException
     */
    public function submitWarrantyRequest(int $ticketId, array $data, array $files): void;

    /**
     * List warranty requests for a ticket (visible on the public ticket page).
     *
     * @return list<array{
     *     id: int,
     *     subject: string,
     *     description: string,
     *     requester_name: string,
     *     created_at: string|null,
     *     attachments: list<array{id: int, url: string|null, original_name: string, mime_type: string, size_bytes: int}>,
     * }>
     */
    public function listWarrantyRequestsByTicketId(int $ticketId): array;

    /**
     * Whether the resident can request warranty for this ticket (order completed + within 12 months).
     */
    public function canRequestWarrantyByTicketId(int $ticketId): bool;

    /**
     * Get acceptance report info for the ticket's order (if any).
     * Returned when the linked order is in accepted/completed status.
     *
     * @return array{
     *     share_token: string,
     *     public_url: string,
     *     is_confirmed: bool,
     *     confirmed_at: string|null,
     *     confirmed_signature_name: string|null,
     *     is_confirmable: bool,
     *     has_signed_file: bool,
     *     signed_file_url: string|null,
     *     signed_file_original_name: string|null,
     *     signed_uploaded_at: string|null,
     * }|null
     */
    public function getAcceptanceReportInfoByTicketId(int $ticketId): ?array;
}
