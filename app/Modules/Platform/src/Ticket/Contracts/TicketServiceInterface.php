<?php

namespace App\Modules\Platform\Ticket\Contracts;

use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;

interface TicketServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): Ticket;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Ticket;

    /**
     * Lookup organization and project names for the ticket form.
     *
     * @return array{org_name: string|null, project_name: string|null}
     */
    public function lookup(?string $orgId, ?int $projectId): array;

    /**
     * Auto-release stale tickets back to pool.
     *
     * @return array{checked: int, released: int}
     */
    public function autoReleaseStaleTickets(): array;

    /**
     * Get public ticket info for rating page.
     */
    public function getPublicTicketInfo(string $code): Ticket;

    /**
     * Submit resident rating for a ticket.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitRating(string $code, array $data): void;

    /**
     * Submit resident quote decision (approve or reject).
     *
     * @throws \App\Common\Exceptions\BusinessException
     */
    public function submitQuoteDecision(string $code, string $action, ?string $reason): void;

    /**
     * Submit a warranty request for a ticket whose order has been completed.
     *
     * @param  array{subject: string, description: string}  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     *
     * @throws \App\Common\Exceptions\BusinessException
     */
    public function submitWarrantyRequest(string $code, array $data, array $files): void;
}
