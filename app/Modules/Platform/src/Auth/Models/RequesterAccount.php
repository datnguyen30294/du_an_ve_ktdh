<?php

namespace App\Modules\Platform\Auth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class RequesterAccount extends Authenticatable
{
    use CentralConnection, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'requester_accounts';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
