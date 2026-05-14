<?php

namespace App\Modules\PMC\ClosingPeriod\Contracts;

use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\Models\Order;

interface CommissionSnapshotServiceInterface
{
    /**
     * Calculate and save commission snapshots for an order in a closing period.
     *
     * @return list<OrderCommissionSnapshot>
     */
    public function createSnapshotsForOrder(ClosingPeriod $period, Order $order): array;

    /**
     * Delete old snapshots and recalculate for an order.
     *
     * @return list<OrderCommissionSnapshot>
     */
    public function recalculateForOrder(ClosingPeriod $period, Order $order): array;
}
