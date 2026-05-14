<?php

namespace App\Modules\PMC\Receivable\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class PaymentReceipt extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'receivable_id',
        'type',
        'amount',
        'payment_method',
        'collected_by_id',
        'note',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentReceiptType::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Receivable, $this>
     */
    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class, 'receivable_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'collected_by_id');
    }

    /**
     * @return HasOne<FinancialReconciliation, $this>
     */
    public function reconciliation(): HasOne
    {
        return $this->hasOne(FinancialReconciliation::class, 'payment_receipt_id');
    }

    protected static function newFactory(): \Database\Factories\Tenant\PaymentReceiptFactory
    {
        return \Database\Factories\Tenant\PaymentReceiptFactory::new();
    }
}
