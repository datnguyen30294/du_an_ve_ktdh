<?php

namespace App\Modules\PMC\Order\AdvancePayment\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class AdvancePaymentRecord extends BaseModel implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'account_id',
        'order_id',
        'order_line_id',
        'amount',
        'note',
        'paid_at',
        'paid_by_id',
        'batch_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class, 'order_line_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_by_id');
    }
}
