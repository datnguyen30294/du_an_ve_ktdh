<?php

namespace App\Modules\PMC\Department\Requests;

use App\Common\Requests\BaseFormRequest;

class CreateDepartmentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
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
            'code.required' => 'Mã phòng ban là bắt buộc.',
            'code.max' => 'Mã phòng ban không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã phòng ban đã tồn tại.',
            'name.required' => 'Tên phòng ban là bắt buộc.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
            'parent_id.exists' => 'Phòng ban cha không tồn tại.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
