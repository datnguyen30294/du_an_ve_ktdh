<?php

namespace App\Modules\PMC\Treasury\Events;

use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class FinancialReconciliationReset implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public FinancialReconciliation $reconciliation) {}
}
