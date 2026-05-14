<?php

namespace App\Modules\PMC\Customer\Requests;

use App\Common\Requests\BaseFormRequest;

class ListCustomerPaymentsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }
}
