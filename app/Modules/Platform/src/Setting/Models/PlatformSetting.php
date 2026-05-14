<?php

namespace App\Modules\Platform\Setting\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    /**
     * Force the central connection. Without this, the model would inherit
     * whichever connection is active at query time — and inside a tenant
     * HTTP request the tenant DB is bound, which does not have this table.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }
}
