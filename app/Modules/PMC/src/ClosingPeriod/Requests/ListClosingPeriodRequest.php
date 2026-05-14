<?php

namespace App\Modules\PMC\ClosingPeriod\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use Illuminate\Validation\Rule;

class ListClosingPeriodRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ClosingPeriodStatus::values())],
            'project_id' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'period_start', 'period_end'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
