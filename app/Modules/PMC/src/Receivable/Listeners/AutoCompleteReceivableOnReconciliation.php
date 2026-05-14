<?php

namespace App\Modules\PMC\Receivable\Listeners;

use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationApproved;

/**
 * Flips a receivable to Completed automatically when the reconciliation that
 * just got approved was the last pending one AND the receivable is fully paid.
 * Prevents users from having to click a separate "Hoàn thành" button.
 */
class AutoCompleteReceivableOnReconciliation
{
    public function __construct(protected ReceivableServiceInterface $service) {}

    public function handle(FinancialReconciliationApproved $event): void
    {
        $receivableId = $event->reconciliation->receivable_id;

        if (! $receivableId) {
            return;
        }

        $this->service->autoCompleteIfReady((int) $receivableId);
    }
}
