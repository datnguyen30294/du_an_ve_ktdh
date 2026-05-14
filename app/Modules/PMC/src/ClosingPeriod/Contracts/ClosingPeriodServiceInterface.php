<?php

namespace App\Modules\PMC\ClosingPeriod\Contracts;

use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClosingPeriodServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): ClosingPeriod;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClosingPeriod;

    /**
     * Get orders eligible to be added to a closing period.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\PMC\Order\Models\Order>
     */
    public function getEligibleOrders(int $periodId): \Illuminate\Database\Eloquent\Collection;

    /**
     * Add orders to a closing period.
     *
     * @param  array<int>  $orderIds
     */
    public function addOrders(int $periodId, array $orderIds): ClosingPeriod;

    /**
     * Remove an order from a closing period.
     */
    public function removeOrder(int $periodId, int $orderId): ClosingPeriod;

    /**
     * Close a period.
     *
     * @param  array<string, mixed>  $data
     */
    public function close(int $periodId, array $data): ClosingPeriod;

    /**
     * Reopen a closed period.
     *
     * @param  array<string, mixed>  $data
     */
    public function reopen(int $periodId, array $data): ClosingPeriod;

    /**
     * Delete a closing period.
     */
    public function delete(int $id): void;

    /**
     * Get commission summary with stats, by-recipient aggregation, and snapshot details.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getCommissionSummary(array $filters): array;

    /**
     * Update payout status for given snapshot IDs.
     *
     * @param  array<int>  $snapshotIds
     */
    public function updatePayoutStatus(array $snapshotIds, PayoutStatus $status): int;
}
