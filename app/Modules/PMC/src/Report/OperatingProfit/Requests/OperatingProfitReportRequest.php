<?php

namespace App\Modules\PMC\Report\OperatingProfit\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class OperatingProfitReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closing_period_id' => ['nullable', 'integer', Rule::exists('closing_periods', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closing_period_id.exists' => 'Kỳ chốt không tồn tại.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'date_from.date_format' => 'Ngày bắt đầu không đúng định dạng (Y-m-d).',
            'date_to.date_format' => 'Ngày kết thúc không đúng định dạng (Y-m-d).',
            'date_to.after_or_equal' => 'Ngày kết thúc phải >= ngày bắt đầu.',
        ];
    }
}
