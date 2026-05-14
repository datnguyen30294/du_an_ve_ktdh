<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationReset;

class SoftDeleteCashTransactionOnReconciliationReset
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(FinancialReconciliationReset $event): void
    {
        $this->service->softDeleteFromReconciliationReset($event->reconciliation);
    }
}
