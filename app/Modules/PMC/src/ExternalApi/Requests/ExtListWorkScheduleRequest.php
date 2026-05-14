<?php

namespace App\Modules\PMC\ExternalApi\Requests;

use App\Common\Requests\BaseFormRequest;

class ExtListWorkScheduleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', 'in:date,created_at,id'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.exists' => 'Nhân viên không tồn tại.',
            'shift_id.exists' => 'Ca không tồn tại.',
            'month.regex' => 'Tháng phải đúng định dạng YYYY-MM.',
            'date_from.date_format' => 'Ngày bắt đầu sai định dạng.',
            'date_to.date_format' => 'Ngày kết thúc sai định dạng.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải >= ngày bắt đầu.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.min' => 'Số dòng/trang tối thiểu là 1.',
            'per_page.max' => 'Số dòng/trang tối đa là 500.',
        ];
    }
}
