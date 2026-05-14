<?php

namespace App\Modules\PMC\Quote\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Enums\ResidentApprovedVia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class Quote extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'og_ticket_id',
        'status',
        'is_active',
        'total_amount',
        'manager_approved_at',
        'manager_approved_by_id',
        'resident_approved_at',
        'resident_approved_via',
        'resident_approved_by_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'is_active' => 'boolean',
            'total_amount' => 'decimal:2',
            'manager_approved_at' => 'datetime',
            'resident_approved_at' => 'datetime',
            'resident_approved_via' => ResidentApprovedVia::class,
        ];
    }

    /**
     * @return BelongsTo<OgTicket, $this>
     */
    public function ogTicket(): BelongsTo
    {
        return $this->belongsTo(OgTicket::class, 'og_ticket_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function managerApprovedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'manager_approved_by_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function residentApprovedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'resident_approved_by_id');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class, 'quote_id');
    }

    /**
     * @return HasOne<Order, $this>
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'quote_id')
            ->where('status', '!=', OrderStatus::Cancelled);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('code', static::likeOperator(), "%{$keyword}%")
                ->orWhereHas('ogTicket', function (Builder $q2) use ($keyword): void {
                    $q2->where('subject', static::likeOperator(), "%{$keyword}%");
                });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, QuoteStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): \Database\Factories\Tenant\QuoteFactory
    {
        return \Database\Factories\Tenant\QuoteFactory::new();
    }
}
