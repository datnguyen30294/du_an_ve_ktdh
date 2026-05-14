<?php

namespace App\Modules\PMC\Order\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class Order extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'quote_id',
        'status',
        'total_amount',
        'completed_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class, 'order_id');
    }

    /**
     * @return HasOne<\App\Modules\PMC\Receivable\Models\Receivable, $this>
     */
    public function receivable(): HasOne
    {
        return $this->hasOne(\App\Modules\PMC\Receivable\Models\Receivable::class, 'order_id');
    }

    /**
     * @return HasMany<OrderCommissionOverride, $this>
     */
    public function commissionOverrides(): HasMany
    {
        return $this->hasMany(OrderCommissionOverride::class, 'order_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('code', static::likeOperator(), "%{$keyword}%")
                ->orWhereHas('quote.ogTicket', function (Builder $q2) use ($keyword): void {
                    $q2->where('subject', static::likeOperator(), "%{$keyword}%");
                });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', OrderStatus::Cancelled->value);
    }

    /**
     * @return HasOne<ClosingPeriodOrder, $this>
     */
    public function closingPeriodOrder(): HasOne
    {
        return $this->hasOne(ClosingPeriodOrder::class, 'order_id');
    }

    /**
     * Check if this order is financially locked (belongs to a closed period).
     */
    public function isFinanciallyLocked(): bool
    {
        return $this->closingPeriodOrder()
            ->whereHas('closingPeriod', fn ($q) => $q->where('status', 'closed'))
            ->exists();
    }

    /**
     * Access OgTicket through Quote relationship.
     */
    public function getOgTicketAttribute(): ?OgTicket
    {
        return $this->quote?->ogTicket;
    }

    protected static function newFactory(): \Database\Factories\Tenant\OrderFactory
    {
        return \Database\Factories\Tenant\OrderFactory::new();
    }
}
