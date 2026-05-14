<?php

namespace App\Modules\PMC\Order\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Order\Enums\CommissionOverrideRecipientType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCommissionOverride extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'recipient_type',
        'account_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_type' => CommissionOverrideRecipientType::class,
            'amount' => 'decimal:2',
        ];
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
        return $this->belongsTo(Account::class);
    }
}
