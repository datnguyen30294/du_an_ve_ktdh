<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;

class SyncOgTicketCategoriesRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'exists:og_ticket_categories,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_ids.present' => 'Danh sách danh mục là bắt buộc.',
            'category_ids.array' => 'Danh sách danh mục không hợp lệ.',
            'category_ids.*.exists' => 'Danh mục không tồn tại.',
        ];
    }
}
