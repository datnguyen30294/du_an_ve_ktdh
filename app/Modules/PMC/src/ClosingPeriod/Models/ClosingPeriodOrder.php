<?php

namespace App\Modules\PMC\ClosingPeriod\Models;

use App\Modules\PMC\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClosingPeriodOrder extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'closing_period_id',
        'order_id',
        'frozen_receivable_amount',
        'frozen_commission_total',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frozen_receivable_amount' => 'decimal:2',
            'frozen_commission_total' => 'decimal:2',
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
     * @return HasMany<OrderCommissionSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrderCommissionSnapshot::class, 'order_id', 'order_id')
            ->where('closing_period_id', $this->closing_period_id);
    }
}
