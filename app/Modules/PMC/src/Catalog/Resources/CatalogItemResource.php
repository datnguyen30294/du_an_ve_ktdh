<?php

namespace App\Modules\PMC\Catalog\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\CatalogItemImage;
use Illuminate\Http\Request;

/**
 * @mixin CatalogItem
 */
class CatalogItemResource extends BaseResource
{
    /**
     * @return array{id: int, type: array{value: string, label: string}, code: string|null, name: string, unit: string, unit_price: string, price_note: string|null, purchase_price: string|null, commission_rate: string|null, description: string|null, content: string|null, slug: string|null, sort_order: int, image_url: string|null, status: array{value: string, label: string}, is_published: bool, is_featured: bool, supplier?: array{id: int, name: string, code: string}|null, service_category?: array{id: int, name: string, code: string}|null, images?: list<array{id: int, image_url: string, sort_order: int}>, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{value: string, label: string} */
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'code' => $this->code,
            'name' => $this->name,
            'unit' => $this->unit,
            /** @var string */
            'unit_price' => $this->unit_price,
            /** @var string|null */
            'price_note' => $this->price_note,
            /** @var string|null */
            'purchase_price' => $this->purchase_price,
            /** @var string|null */
            'commission_rate' => $this->commission_rate,
            'description' => $this->description,
            'content' => $this->content,
            'slug' => $this->slug,
            /** @var int */
            'sort_order' => $this->sort_order,
            /** @var string|null */
            'image_url' => $this->image_url,
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var bool */
            'is_published' => $this->is_published,
            /** @var bool */
            'is_featured' => $this->is_featured,
            /** @var array{id: int, name: string, code: string}|null */
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code,
            ]),
            /** @var array{id: int, name: string, code: string}|null */
            'service_category' => $this->whenLoaded('serviceCategory', fn () => [
                'id' => $this->serviceCategory->id,
                'name' => $this->serviceCategory->name,
                'code' => $this->serviceCategory->code,
            ]),
            /** @var list<array{id: int, image_url: string, sort_order: int}> */
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (CatalogItemImage $img) => [
                'id' => $img->id,
                'image_url' => $img->image_url,
                'sort_order' => $img->sort_order,
            ])->values()->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
