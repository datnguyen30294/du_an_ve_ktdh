<?php

namespace App\Common\Traits;

use App\Common\Models\TenantAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTenantAttachments
{
    /**
     * @return MorphMany<TenantAttachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(TenantAttachment::class, 'attachable');
    }
}
