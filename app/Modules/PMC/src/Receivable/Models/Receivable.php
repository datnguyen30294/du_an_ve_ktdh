<?php

namespace App\Modules\PMC\Receivable\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Receivable extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'order_id',
        'project_id',
        'amount',
        'paid_amount',
        'status',
        'due_date',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReceivableStatus::class,
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'issued_at' => 'datetime',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return HasMany<PaymentReceipt, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'receivable_id');
    }

    /**
     * @return HasMany<FinancialReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(FinancialReconciliation::class, 'receivable_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->whereHas('order', function (Builder $q2) use ($keyword): void {
                $q2->where('code', static::likeOperator(), "%{$keyword}%");
            })->orWhereHas('order.quote.ogTicket', function (Builder $q2) use ($keyword): void {
                $q2->where('requester_name', static::likeOperator(), "%{$keyword}%")
                    ->orWhere('apartment_name', static::likeOperator(), "%{$keyword}%");
            })->orWhereHas('order.quote.ogTicket.customer', function (Builder $q2) use ($keyword): void {
                $q2->where('full_name', static::likeOperator(), "%{$keyword}%")
                    ->orWhere('phone', static::likeOperator(), "%{$keyword}%");
            });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, ReceivableStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ReceivableStatus::Unpaid->value,
            ReceivableStatus::Partial->value,
            ReceivableStatus::Overdue->value,
        ]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ReceivableStatus::WrittenOff->value,
            ReceivableStatus::Completed->value,
        ]);
    }

    /**
     * Get outstanding amount.
     */
    public function getOutstandingAttribute(): string
    {
        return number_format(max(0, (float) $this->amount - (float) $this->paid_amount), 2, '.', '');
    }

    /**
     * Get overpaid amount (only positive when paid_amount > amount).
     */
    public function getOverpaidAmountAttribute(): string
    {
        $diff = (float) $this->paid_amount - (float) $this->amount;

        return number_format(max(0, $diff), 2, '.', '');
    }

    /**
     * Get aging days (only for outstanding receivables).
     */
    public function getAgingDaysAttribute(): int
    {
        if (in_array($this->status, [ReceivableStatus::Paid, ReceivableStatus::Overpaid, ReceivableStatus::Completed, ReceivableStatus::WrittenOff])) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()->diffInDays($this->due_date, false) * -1);
    }

    protected static function newFactory(): \Database\Factories\Tenant\ReceivableFactory
    {
        return \Database\Factories\Tenant\ReceivableFactory::new();
    }
}
