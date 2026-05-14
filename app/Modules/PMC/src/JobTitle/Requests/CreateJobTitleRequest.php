<?php

namespace App\Modules\PMC\JobTitle\Requests;

use App\Common\Requests\BaseFormRequest;

class CreateJobTitleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'code' => ['required', 'string', 'max:50', 'unique:job_titles,code'],
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
            'code.required' => 'Mã chức danh là bắt buộc.',
            'code.max' => 'Mã chức danh không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã chức danh đã tồn tại.',
            'name.required' => 'Tên chức danh là bắt buộc.',
            'name.max' => 'Tên chức danh không được vượt quá 255 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
