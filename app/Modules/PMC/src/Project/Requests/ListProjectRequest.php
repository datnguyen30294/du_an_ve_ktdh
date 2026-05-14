<?php

namespace App\Modules\PMC\Project\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use Illuminate\Validation\Rule;

class ListProjectRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ProjectStatus::values())],
            'sort_by' => ['nullable', 'string', 'in:name,code,status,created_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
