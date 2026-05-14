<?php

namespace App\Modules\PMC\Order\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderLine extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'line_type',
        'reference_id',
        'name',
        'quantity',
        'unit',
        'unit_price',
        'purchase_price',
        'advance_payer_id',
        'line_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => QuoteLineType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'line_amount' => 'decimal:2',
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
    public function advancePayer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'advance_payer_id');
    }

    /**
     * @return HasMany<\App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord, $this>
     */
    public function advancePaymentRecords(): HasMany
    {
        return $this->hasMany(\App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord::class, 'order_line_id');
    }

    /**
     * Total advance amount for this line (purchase_price × quantity).
     */
    public function advanceAmount(): float
    {
        if ($this->purchase_price === null) {
            return 0.0;
        }

        return (float) $this->purchase_price * $this->quantity;
    }

    /**
     * Derived status: 'none' | 'pending' | 'paid'.
     * Expects `advancePaymentRecords` relation to be loaded (or counted).
     */
    public function advanceStatus(): string
    {
        if ($this->advance_payer_id === null) {
            return 'none';
        }

        $paidCount = $this->relationLoaded('advancePaymentRecords')
            ? $this->advancePaymentRecords->count()
            : $this->advancePaymentRecords()->count();

        return $paidCount > 0 ? 'paid' : 'pending';
    }

    protected static function newFactory(): \Database\Factories\Tenant\OrderLineFactory
    {
        return \Database\Factories\Tenant\OrderLineFactory::new();
    }
}
