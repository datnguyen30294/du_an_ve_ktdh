<?php

namespace App\Modules\PMC\Receivable\Contracts;

use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReceivableServiceInterface
{
    /**
     * List receivables with filters and pagination.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Receivable;

    /**
     * Get summary KPI and aging buckets.
     *
     * @return array{kpi: array<string, mixed>, aging: list<array<string, mixed>>}
     */
    public function summary(?int $projectId = null): array;

    /**
     * Record a payment for a receivable.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(int $receivableId, array $data): Receivable;

    /**
     * Update a payment receipt and recalculate receivable totals.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePayment(int $receivableId, int $paymentId, array $data): Receivable;

    /**
     * Record a refund for an overpaid receivable.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordRefund(int $receivableId, array $data): Receivable;

    /**
     * Mark a receivable as completed (paid + all reconciled).
     */
    public function markCompleted(int $receivableId): Receivable;

    /**
     * Silently mark a receivable as completed when it is fully paid and every
     * payment has been reconciled. Returns true when the status was flipped
     * to Completed. Never throws — intended for event-driven auto-completion.
     */
    public function autoCompleteIfReady(int $receivableId): bool;

    /**
     * Write off a receivable.
     *
     * @param  array<string, mixed>  $data
     */
    public function writeOff(int $receivableId, array $data): Receivable;

    /**
     * Create a receivable when an order is confirmed.
     */
    public function createFromOrder(Order $order): Receivable;

    /**
     * Sync receivable amount with the order total_amount and recompute status.
     * No-op when receivable is missing (WrittenOff is filtered upstream) or
     * when the amount is unchanged. A Completed receivable regresses to
     * Paid/Partial/Overpaid so the money position stays consistent with the
     * new quote. Reconciliation records are left untouched.
     */
    public function syncAmountFromOrder(Order $order): void;

    /**
     * Handle order cancellation side effects.
     */
    public function handleOrderCancelled(Order $order): void;

    /**
     * Get audit history for a receivable (including payment receipt audits).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAudits(int $id): array;
}
