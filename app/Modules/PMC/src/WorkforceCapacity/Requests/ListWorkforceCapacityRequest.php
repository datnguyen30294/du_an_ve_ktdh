<?php

namespace App\Modules\PMC\WorkforceCapacity\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ListWorkforceCapacityRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.integer' => 'Dự án phải là số nguyên.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'search.string' => 'Từ khóa phải là chuỗi.',
            'search.max' => 'Từ khóa tìm tối đa 100 ký tự.',
        ];
    }
}
