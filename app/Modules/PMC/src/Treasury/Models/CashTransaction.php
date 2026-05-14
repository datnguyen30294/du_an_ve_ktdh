<?php

namespace App\Modules\PMC\Treasury\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class CashTransaction extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'cash_account_id',
        'direction',
        'amount',
        'category',
        'transaction_date',
        'financial_reconciliation_id',
        'commission_snapshot_id',
        'advance_payment_record_id',
        'order_id',
        'note',
        'created_by_id',
        'deleted_by_id',
        'delete_reason',
        'auto_deleted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CashTransactionDirection::class,
            'category' => CashTransactionCategory::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'auto_deleted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * The reconciliation record that GENERATED this cash transaction (receivable flow).
     *
     * @return BelongsTo<FinancialReconciliation, $this>
     */
    public function financialReconciliation(): BelongsTo
    {
        return $this->belongsTo(FinancialReconciliation::class, 'financial_reconciliation_id');
    }

    /**
     * The reconciliation record attached to this manual cash transaction (manual flow).
     * A manual topup/withdraw creates both the cash tx and a pending reconciliation.
     *
     * @return HasOne<FinancialReconciliation, $this>
     */
    public function manualReconciliation(): HasOne
    {
        return $this->hasOne(FinancialReconciliation::class, 'cash_transaction_id');
    }

    /**
     * @return BelongsTo<OrderCommissionSnapshot, $this>
     */
    public function commissionSnapshot(): BelongsTo
    {
        return $this->belongsTo(OrderCommissionSnapshot::class, 'commission_snapshot_id');
    }

    /**
     * @return BelongsTo<AdvancePaymentRecord, $this>
     */
    public function advancePaymentRecord(): BelongsTo
    {
        return $this->belongsTo(AdvancePaymentRecord::class, 'advance_payment_record_id');
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deleted_by_id');
    }

    public function isManual(): bool
    {
        return $this->category?->isManual() ?? false;
    }

    public function canBeSoftDeletedByUser(): bool
    {
        return $this->isManual() && ! $this->trashed();
    }

    public function sourceLabel(): string
    {
        if ($this->financial_reconciliation_id !== null) {
            return 'Đối soát #'.$this->financial_reconciliation_id;
        }

        if ($this->commission_snapshot_id !== null) {
            return 'Snapshot hoa hồng #'.$this->commission_snapshot_id;
        }

        if ($this->advance_payment_record_id !== null) {
            return 'Ứng vật tư #'.$this->advance_payment_record_id;
        }

        return 'Thủ công';
    }
}
