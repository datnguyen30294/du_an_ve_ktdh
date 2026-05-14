<?php

namespace App\Modules\PMC\OgTicket\Models;

use App\Common\Models\BaseModel;
use App\Common\Traits\HasTenantAttachments;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Customer\Models\Customer;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class OgTicket extends BaseModel implements Auditable
{
    use HasTenantAttachments, \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'ticket_id',
        'customer_id',
        'requester_name',
        'requester_phone',
        'apartment_name',
        'project_id',
        'subject',
        'description',
        'address',
        'latitude',
        'longitude',
        'channel',
        'status',
        'completed_at',
        'priority',
        'internal_note',
        'received_at',
        'received_by_id',
        'sla_quote_due_at',
        'sla_completion_due_at',
        'resident_rating',
        'resident_rating_comment',
        'resident_rated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OgTicketStatus::class,
            'priority' => OgTicketPriority::class,
            'channel' => TicketChannel::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
            'sla_quote_due_at' => 'datetime',
            'sla_completion_due_at' => 'datetime',
            'resident_rating' => 'integer',
            'resident_rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'received_by_id');
    }

    /**
     * @return BelongsToMany<Account, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'og_ticket_assignees')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return HasMany<OgTicketLifecycleSegment, $this>
     */
    public function lifecycleSegments(): HasMany
    {
        return $this->hasMany(OgTicketLifecycleSegment::class)->orderBy('started_at')->orderBy('id');
    }

    /**
     * @return HasMany<OgTicketWarrantyRequest, $this>
     */
    public function warrantyRequests(): HasMany
    {
        return $this->hasMany(OgTicketWarrantyRequest::class)->oldest();
    }

    /**
     * Active quote (is_active=true). One ticket has at most one active quote at a time.
     *
     * @return HasOne<Quote, $this>
     */
    public function activeQuote(): HasOne
    {
        return $this->hasOne(Quote::class, 'og_ticket_id')->where('is_active', true);
    }

    /**
     * @return HasOne<OgTicketSurvey, $this>
     */
    public function survey(): HasOne
    {
        return $this->hasOne(OgTicketSurvey::class, 'og_ticket_id');
    }

    /**
     * @return BelongsToMany<OgTicketCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(OgTicketCategory::class, 'og_ticket_category_links')
            ->withTimestamps()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Scope: đang xử lý (status != cancelled).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', OgTicketStatus::Cancelled->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('subject', static::likeOperator(), "%{$keyword}%")
                ->orWhere('requester_name', static::likeOperator(), "%{$keyword}%")
                ->orWhere('requester_phone', static::likeOperator(), "%{$keyword}%")
                ->orWhereHas('customer', function (Builder $q2) use ($keyword): void {
                    $q2->where('full_name', static::likeOperator(), "%{$keyword}%")
                        ->orWhere('phone', static::likeOperator(), "%{$keyword}%");
                });
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, OgTicketStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByPriority(Builder $query, OgTicketPriority $priority): Builder
    {
        return $query->where('priority', $priority->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeHasWarrantyRequest(Builder $query, bool $has): Builder
    {
        return $has
            ? $query->has('warrantyRequests')
            : $query->doesntHave('warrantyRequests');
    }

    protected static function newFactory(): \Database\Factories\Tenant\OgTicketFactory
    {
        return \Database\Factories\Tenant\OgTicketFactory::new();
    }
}
