<?php

namespace App\Modules\PMC\WorkSchedule\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpsertWorkScheduleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'account_code' => [
                'required',
                'string',
                Rule::exists('accounts', 'employee_code')->whereNull('deleted_at'),
            ],
            'project_code' => [
                'required',
                'string',
                Rule::exists('projects', 'code')->whereNull('deleted_at'),
            ],
            'shift_code' => ['required', 'string', 'exists:shifts,code'],
            'date' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
            'external_ref' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('work_schedules', 'external_ref')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_code.required' => 'Mã nhân viên là bắt buộc.',
            'account_code.exists' => 'Mã nhân viên không tồn tại.',
            'project_code.required' => 'Mã dự án là bắt buộc.',
            'project_code.exists' => 'Mã dự án không tồn tại.',
            'shift_code.required' => 'Mã ca là bắt buộc.',
            'shift_code.exists' => 'Mã ca không tồn tại.',
            'date.required' => 'Ngày là bắt buộc.',
            'date.date_format' => 'Ngày không đúng định dạng YYYY-MM-DD.',
            'note.max' => 'Ghi chú tối đa 255 ký tự.',
            'external_ref.max' => 'External ref tối đa 255 ký tự.',
            'external_ref.unique' => 'External ref đã tồn tại.',
        ];
    }
}
