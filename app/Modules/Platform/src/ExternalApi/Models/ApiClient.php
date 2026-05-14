<?php

namespace App\Modules\Platform\ExternalApi\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class ApiClient extends Model
{
    use CentralConnection, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'client_key',
        'encrypted_secret',
        'scopes',
        'is_active',
        'last_used_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'encrypted_secret' => 'encrypted',
        ];
    }

    protected static function newFactory(): \Database\Factories\Platform\ApiClientFactory
    {
        return \Database\Factories\Platform\ApiClientFactory::new();
    }
}
