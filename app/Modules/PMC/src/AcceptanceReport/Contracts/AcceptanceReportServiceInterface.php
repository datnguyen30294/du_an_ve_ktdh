<?php

namespace App\Modules\PMC\AcceptanceReport\Contracts;

use App\Modules\PMC\AcceptanceReport\Models\AcceptanceReport;
use Illuminate\Http\UploadedFile;

interface AcceptanceReportServiceInterface
{
    /**
     * Get report for order, creating one from the template if none exists yet.
     */
    public function getOrCreateForOrder(int $orderId): AcceptanceReport;

    /**
     * Update content / customer info for an existing report (by id).
     *
     * @param  array{content_html?: string, customer_name?: ?string, customer_phone?: ?string, note?: ?string}  $data
     */
    public function update(int $id, array $data): AcceptanceReport;

    /**
     * Update via public share token (no auth).
     *
     * @param  array{content_html?: string, customer_name?: ?string, customer_phone?: ?string, note?: ?string}  $data
     */
    public function updateByToken(string $token, array $data): AcceptanceReport;

    public function findByOrderId(int $orderId): ?AcceptanceReport;

    public function findByToken(string $token): ?AcceptanceReport;

    public function delete(int $id): void;

    /**
     * Re-render the current template into an existing report (by id).
     * Overwrites content_html, keeps share_token / customer fields / note.
     */
    public function regenerate(int $id): AcceptanceReport;

    /**
     * Render the current template for an order: replace placeholders with order context data.
     * If a report is provided, its overrides (customer_name/phone) take precedence over the
     * fallback data pulled from the originating ogTicket.
     */
    public function renderTemplate(int $orderId, ?AcceptanceReport $report = null): string;

    /**
     * Confirm the report by resident via share token.
     *
     * @param  array{signature_name: string, note?: ?string}  $data
     */
    public function confirmByToken(string $token, array $data): AcceptanceReport;

    /**
     * Upload signed (scanned) acceptance report file for an order.
     */
    public function uploadSignedFile(int $orderId, UploadedFile $file): AcceptanceReport;

    /**
     * Remove the uploaded signed file for an order's acceptance report.
     */
    public function deleteSignedFile(int $orderId): AcceptanceReport;
}
