<?php

namespace App\Modules\PMC\Order\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface OrderCommissionOverrideServiceInterface
{
    /**
     * Get override data for an order.
     *
     * @return array{has_overrides: bool, commissionable_total: float, platform_amount: float, overrides: Collection}
     */
    public function getOverrides(int $orderId): array;

    /**
     * Save (replace) commission overrides for an order.
     *
     * @param  array<string, mixed>  $data
     * @return array{has_overrides: bool, commissionable_total: float, platform_amount: float, overrides: Collection}
     */
    public function saveOverrides(int $orderId, array $data): array;

    /**
     * Delete all commission overrides for an order.
     */
    public function deleteOverrides(int $orderId): void;

    /**
     * Check if the given account is a commission adjuster for the order's project.
     */
    public function isAdjuster(int $orderId): bool;
}
