<?php

namespace App\Modules\PMC\JobTitle\Requests;

use App\Common\Requests\BaseFormRequest;

class UpdateJobTitleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
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
            'name.required' => 'Tên chức danh là bắt buộc.',
            'name.max' => 'Tên chức danh không được vượt quá 255 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
