<?php

namespace App\Modules\PMC\Shift\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use Illuminate\Validation\Rule;

class ListShiftRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ShiftStatusEnum::values())],
            'type' => ['nullable', 'string', 'max:50'],
            'work_group' => ['nullable', 'string', 'max:50'],
            'only_active' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'max:50'],
            'sort_by' => ['nullable', 'string', 'in:sort_order,code,name,created_at'],
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
            'project_id.integer' => 'Dự án không hợp lệ.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'search.max' => 'Từ khoá tìm kiếm không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'type.max' => 'Kiểu ca không được vượt quá 50 ký tự.',
            'work_group.max' => 'Nhóm xử lý không được vượt quá 50 ký tự.',
            'only_active.boolean' => 'Tham số only_active không hợp lệ.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }
}
