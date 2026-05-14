<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Catalog\Enums\SupplierStatus;
use Illuminate\Validation\Rule;

class ListCatalogSupplierRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(SupplierStatus::class)],
            'sort_by' => ['nullable', 'string', 'in:name,code,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
