<?php

namespace App\Common\Traits;

use App\Common\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
