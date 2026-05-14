<?php

namespace App\Modules\PMC\OgTicket\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\OgTicket\Models\OgTicketSurvey;
use Illuminate\Http\Request;

/**
 * @mixin OgTicketSurvey
 */
class OgTicketSurveyResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'og_ticket_id' => $this->og_ticket_id,
            /** @var string|null */
            'note' => $this->note,
            /** @var int|null */
            'surveyed_by' => $this->surveyed_by,
            /** @var array{id: int, name: string}|null */
            'surveyor' => $this->whenLoaded('surveyor', fn () => $this->surveyor ? [
                'id' => $this->surveyor->id,
                'name' => $this->surveyor->name,
            ] : null),
            /** @var string|null */
            'surveyed_at' => optional($this->surveyed_at)->toIso8601String(),
            /** @var list<array{id: int, file_path: string, original_name: string, mime_type: string, size_bytes: int, url: string|null}> */
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($a) => [
                'id' => $a->id,
                'file_path' => $a->file_path,
                'original_name' => $a->original_name,
                'mime_type' => $a->mime_type,
                'size_bytes' => (int) $a->size_bytes,
                'url' => $a->url,
            ])->all(), []),
            /** @var string|null */
            'created_at' => optional($this->created_at)->toIso8601String(),
            /** @var string|null */
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
