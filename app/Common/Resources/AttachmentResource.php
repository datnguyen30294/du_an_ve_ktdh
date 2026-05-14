<?php

namespace App\Common\Resources;

use App\Common\Models\Attachment;
use Illuminate\Http\Request;

/**
 * @mixin Attachment
 */
class AttachmentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            /** @var int */
            'size_bytes' => $this->size_bytes,
            /** @var string|null */
            'url' => $this->url,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
