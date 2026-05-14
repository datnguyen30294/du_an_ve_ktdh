<?php

namespace App\Modules\PMC\ClosingPeriod\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateClosingPeriodRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên kỳ chốt là bắt buộc.',
            'name.max' => 'Tên kỳ chốt không được vượt quá 255 ký tự.',
            'period_start.required' => 'Ngày bắt đầu là bắt buộc.',
            'period_start.date' => 'Ngày bắt đầu không hợp lệ.',
            'period_end.required' => 'Ngày kết thúc là bắt buộc.',
            'period_end.date' => 'Ngày kết thúc không hợp lệ.',
            'period_end.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
            'project_id.exists' => 'Dự án không tồn tại.',
        ];
    }
}
