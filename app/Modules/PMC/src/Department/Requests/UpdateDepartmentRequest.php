<?php

namespace App\Modules\PMC\Department\Requests;

use App\Common\Requests\BaseFormRequest;

class UpdateDepartmentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.exists' => 'Dự án không tồn tại.',
            'name.required' => 'Tên phòng ban là bắt buộc.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
            'parent_id.exists' => 'Phòng ban cha không tồn tại.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
