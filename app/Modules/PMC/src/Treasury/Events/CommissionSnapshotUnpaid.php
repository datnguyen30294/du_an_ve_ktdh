<?php

namespace App\Modules\PMC\Treasury\Events;

use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CommissionSnapshotUnpaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public OrderCommissionSnapshot $snapshot) {}
}
