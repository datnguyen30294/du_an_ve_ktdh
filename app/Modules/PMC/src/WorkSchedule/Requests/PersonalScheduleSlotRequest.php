<?php

namespace App\Modules\PMC\WorkSchedule\Requests;

use App\Common\Requests\BaseFormRequest;

class PersonalScheduleSlotRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Nhân viên là bắt buộc.',
            'account_id.exists' => 'Nhân viên không tồn tại.',
            'month.required' => 'Tháng là bắt buộc.',
            'month.regex' => 'Tháng phải đúng định dạng YYYY-MM.',
        ];
    }
}
