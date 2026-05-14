<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use Illuminate\Validation\Rule;

class ListCatalogItemRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::enum(CatalogItemType::class)],
            'supplier_id' => ['nullable', 'integer', 'exists:catalog_suppliers,id'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'status' => ['nullable', 'string', Rule::enum(CatalogStatus::class)],
            'sort_by' => ['nullable', 'string', 'in:name,code,unit_price,type,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
