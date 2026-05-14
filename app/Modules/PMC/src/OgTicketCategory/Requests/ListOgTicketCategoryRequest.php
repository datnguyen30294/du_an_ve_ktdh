<?php

namespace App\Modules\PMC\OgTicketCategory\Requests;

use App\Common\Requests\BaseFormRequest;

class ListOgTicketCategoryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:name,code,sort_order,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
