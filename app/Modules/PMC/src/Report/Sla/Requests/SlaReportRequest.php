<?php

namespace App\Modules\PMC\Report\Sla\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SlaReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.exists' => 'Dự án không tồn tại.',
            'date_from.date_format' => 'Ngày bắt đầu không đúng định dạng.',
            'date_to.date_format' => 'Ngày kết thúc không đúng định dạng.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải >= ngày bắt đầu.',
            'months.integer' => 'Số tháng phải là số nguyên.',
            'months.min' => 'Số tháng phải >= 1.',
            'months.max' => 'Số tháng tối đa là 24.',
            'per_page.integer' => 'Số bản ghi/trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi/trang phải >= 1.',
            'per_page.max' => 'Số bản ghi/trang tối đa là 100.',
        ];
    }
}
