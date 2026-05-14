<?php

namespace App\Modules\PMC\Commission\Requests;

use App\Common\Requests\BaseFormRequest;

class ListCommissionProjectRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:name,code'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Từ khóa tìm kiếm không được vượt quá 255 ký tự.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp phải là tăng dần hoặc giảm dần.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang phải ít nhất là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang không được vượt quá 100.',
        ];
    }
}
