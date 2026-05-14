<?php

namespace App\Modules\PMC\Receivable\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ReceivableSummaryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
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
        ];
    }
}
