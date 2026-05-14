<?php

namespace App\Modules\Platform\Customer\Models;

use App\Common\Models\BaseModel;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Customer extends Model
{
    use CentralConnection, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
    ];

    /**
     * Route mail notifications for this customer. Returns null when the
     * customer has no email, causing the mail channel to skip silently.
     */
    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword): void {
            $q->where('name', BaseModel::likeOperator(), "%{$keyword}%")
                ->orWhere('phone', BaseModel::likeOperator(), "%{$keyword}%");
        });
    }

    protected static function newFactory(): \Database\Factories\Platform\CustomerFactory
    {
        return \Database\Factories\Platform\CustomerFactory::new();
    }
}
