<?php

namespace App\Modules\PMC\Catalog\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Http\Request;

/**
 * @mixin ServiceCategory
 */
class ServiceCategoryResource extends BaseResource
{
    /**
     * @return array{id: int, name: string, code: string, description: string|null, image_url: string|null, sort_order: int, status: array{value: string, label: string}, items_count?: int, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            /** @var string|null */
            'image_url' => $this->image_url,
            /** @var int */
            'sort_order' => $this->sort_order,
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var int */
            'items_count' => $this->whenCounted('items'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
