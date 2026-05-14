<?php

namespace App\Modules\PMC\Shift\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');
        $shift = \App\Modules\PMC\Shift\Models\Shift::query()->find($id);
        $projectId = $shift?->project_id;

        return [
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('shifts', 'code')
                    ->where(fn ($q) => $q->where('project_id', $projectId))
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'work_group' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'status' => ['required', Rule::in(ShiftStatusEnum::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Mã ca là bắt buộc.',
            'code.max' => 'Mã ca không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã ca đã tồn tại trong dự án này.',
            'name.required' => 'Tên ca là bắt buộc.',
            'name.max' => 'Tên ca không được vượt quá 100 ký tự.',
            'type.required' => 'Kiểu ca là bắt buộc.',
            'type.max' => 'Kiểu ca không được vượt quá 50 ký tự.',
            'work_group.required' => 'Nhóm xử lý là bắt buộc.',
            'work_group.max' => 'Nhóm xử lý không được vượt quá 50 ký tự.',
            'start_time.required' => 'Giờ bắt đầu là bắt buộc.',
            'start_time.date_format' => 'Giờ bắt đầu phải có định dạng HH:mm.',
            'end_time.required' => 'Giờ kết thúc là bắt buộc.',
            'end_time.date_format' => 'Giờ kết thúc phải có định dạng HH:mm.',
            'break_hours.numeric' => 'Giờ nghỉ phải là số.',
            'break_hours.min' => 'Giờ nghỉ tối thiểu là 0.',
            'break_hours.max' => 'Giờ nghỉ tối đa là 24.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'sort_order.integer' => 'Thứ tự phải là số nguyên.',
            'sort_order.min' => 'Thứ tự tối thiểu là 0.',
        ];
    }
}
