<?php

namespace App\Modules\PMC\Reconciliation\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinancialReconciliation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'receivable_id',
        'payment_receipt_id',
        'cash_transaction_id',
        'status',
        'amount',
        'reconciled_at',
        'reconciled_by_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReconciliationStatus::class,
            'amount' => 'decimal:2',
            'reconciled_at' => 'datetime',
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
     * @return BelongsTo<PaymentReceipt, $this>
     */
    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'payment_receipt_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reconciled_by_id');
    }

    /**
     * The active (non-deleted) cash transaction generated for this reconciliation.
     * Used by the receivable flow: reconciling a payment receipt creates a cash tx.
     *
     * @return HasOne<CashTransaction, $this>
     */
    public function cashTransaction(): HasOne
    {
        return $this->hasOne(CashTransaction::class, 'financial_reconciliation_id');
    }

    /**
     * The manual cash transaction that this reconciliation was created from.
     * Used by the manual flow: user records a topup/withdraw → cash tx exists first,
     * then a reconciliation record is attached as an audit gate.
     *
     * @return BelongsTo<CashTransaction, $this>
     */
    public function sourceCashTransaction(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class, 'cash_transaction_id');
    }

    public function isManualSource(): bool
    {
        return $this->cash_transaction_id !== null;
    }

    public function isReceivableSource(): bool
    {
        return $this->payment_receipt_id !== null;
    }
}
