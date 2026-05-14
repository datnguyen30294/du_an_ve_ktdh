<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotUnpaid;

class SoftDeleteCashTransactionOnCommissionUnpaid
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(CommissionSnapshotUnpaid $event): void
    {
        $this->service->softDeleteFromCommissionUnpaid($event->snapshot);
    }
}
