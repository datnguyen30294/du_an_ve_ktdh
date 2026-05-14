<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;

class ListPublicServiceRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Từ khoá tìm kiếm không được vượt quá 255 ký tự.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }
}
