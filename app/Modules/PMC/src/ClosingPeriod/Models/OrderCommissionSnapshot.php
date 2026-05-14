<?php

namespace App\Modules\PMC\ClosingPeriod\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderCommissionSnapshot extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'closing_period_id',
        'order_id',
        'recipient_type',
        'account_id',
        'recipient_name',
        'value_type',
        'percent',
        'value_fixed',
        'amount',
        'resolved_from',
        'payout_status',
        'paid_out_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_type' => SnapshotRecipientType::class,
            'value_type' => CommissionValueType::class,
            'percent' => 'decimal:2',
            'value_fixed' => 'decimal:2',
            'amount' => 'decimal:2',
            'payout_status' => PayoutStatus::class,
            'paid_out_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClosingPeriod, $this>
     */
    public function closingPeriod(): BelongsTo
    {
        return $this->belongsTo(ClosingPeriod::class, 'closing_period_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * The active (non-deleted) cash transaction generated when this snapshot was paid.
     *
     * @return HasOne<CashTransaction, $this>
     */
    public function cashTransaction(): HasOne
    {
        return $this->hasOne(CashTransaction::class, 'commission_snapshot_id');
    }
}
