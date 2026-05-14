<?php

namespace App\Modules\Platform\Ticket\Models;

use App\Common\Models\BaseModel;
use App\Common\Traits\HasAttachments;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Ticket extends Model
{
    use CentralConnection, HasAttachments, HasFactory;

    /**
     * Thời gian (phút) tối đa cho phép OG ticket không thay đổi status
     * trước khi tự động thu hồi về pool.
     */
    public const STALE_TIMEOUT_MINUTES = 60;

    protected $fillable = [
        'code',
        'customer_id',
        'requester_name',
        'requester_phone',
        'subject',
        'description',
        'address',
        'latitude',
        'longitude',
        'status',
        'channel',
        'project_id',
        'claimed_by_org_id',
        'claimed_at',
        'is_from_pool',
        'resident_rating',
        'resident_rating_comment',
        'resident_rated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'channel' => TicketChannel::class,
            'claimed_at' => 'datetime',
            'is_from_pool' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'resident_rating' => 'integer',
            'resident_rated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope: ticket chờ xử lý, chưa ai nhận.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('claimed_by_org_id')
            ->where('status', TicketStatus::Pending->value);
    }

    /**
     * Scope: tìm theo subject, description, requester_name, requester_phone.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('subject', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('description', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('requester_name', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('requester_phone', BaseModel::likeOperator(), "%{$keyword}%");
        });
    }

    protected static function newFactory(): \Database\Factories\Platform\TicketFactory
    {
        return \Database\Factories\Platform\TicketFactory::new();
    }
}
