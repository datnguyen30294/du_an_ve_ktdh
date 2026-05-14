<?php

namespace App\Modules\PMC\WorkSchedule\Requests;

use App\Common\Requests\BaseFormRequest;

class ScheduleSlotDetailRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
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
            'date.required' => 'Ngày là bắt buộc.',
            'date.date_format' => 'Ngày phải đúng định dạng YYYY-MM-DD.',
            'shift_id.required' => 'Ca là bắt buộc.',
            'shift_id.exists' => 'Ca không tồn tại.',
        ];
    }
}
