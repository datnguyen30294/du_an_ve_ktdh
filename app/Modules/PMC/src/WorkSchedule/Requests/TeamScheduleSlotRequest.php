<?php

namespace App\Modules\PMC\WorkSchedule\Requests;

use App\Common\Requests\BaseFormRequest;

class TeamScheduleSlotRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'strict_project' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.required' => 'Tháng là bắt buộc.',
            'month.regex' => 'Tháng phải đúng định dạng YYYY-MM.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'account_ids.*.exists' => 'Một trong các nhân viên không tồn tại.',
        ];
    }
}
