<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\AdvancePaymentDeleted;

class SoftDeleteCashTransactionOnAdvancePaymentDeleted
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(AdvancePaymentDeleted $event): void
    {
        $this->service->softDeleteFromAdvancePaymentDeleted($event->record);
    }
}
