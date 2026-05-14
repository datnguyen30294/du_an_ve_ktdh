<?php

namespace App\Modules\PMC\Catalog\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Illuminate\Http\Request;

/**
 * @mixin CatalogSupplier
 */
class CatalogSupplierResource extends BaseResource
{
    /**
     * @return array{id: int, name: string, code: string, contact: string, phone: string, address: string, email: string, commission_rate: string|null, status: array{value: string, label: string}, items_count?: int, created_at: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'contact' => $this->contact,
            'phone' => $this->phone,
            'address' => $this->address,
            'email' => $this->email,
            /** @var string|null */
            'commission_rate' => $this->commission_rate,
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
