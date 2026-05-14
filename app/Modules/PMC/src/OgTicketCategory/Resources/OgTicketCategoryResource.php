<?php

namespace App\Modules\PMC\OgTicketCategory\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use Illuminate\Http\Request;

/**
 * @mixin OgTicketCategory
 */
class OgTicketCategoryResource extends BaseResource
{
    /**
     * @return array{id: int, name: string, code: string, sort_order: int, og_tickets_count?: int, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            /** @var int */
            'sort_order' => $this->sort_order,
            /** @var int */
            'og_tickets_count' => $this->whenCounted('ogTickets'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
