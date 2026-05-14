<?php

namespace App\Modules\PMC\Treasury\Events;

use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AdvancePaymentDeleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public AdvancePaymentRecord $record) {}
}
