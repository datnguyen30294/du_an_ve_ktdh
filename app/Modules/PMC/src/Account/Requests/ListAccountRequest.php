<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;

class ListAccountRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'job_title_id' => ['nullable', 'integer', 'exists:job_titles,id'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'in:name,email,employee_code,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
