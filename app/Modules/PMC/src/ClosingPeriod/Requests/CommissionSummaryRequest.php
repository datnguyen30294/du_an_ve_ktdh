<?php

namespace App\Modules\PMC\ClosingPeriod\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use Illuminate\Validation\Rule;

class CommissionSummaryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closing_period_id' => ['required', 'string'],
            'project_id' => ['nullable', 'integer'],
            'recipient_type' => ['nullable', 'string', Rule::in(SnapshotRecipientType::values())],
            'resolved_from' => ['nullable', 'string', Rule::in(['override', 'config'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closing_period_id.required' => 'Vui lòng chọn kỳ chốt.',
            'recipient_type.in' => 'Loại người nhận không hợp lệ.',
            'resolved_from.in' => 'Nguồn tính không hợp lệ.',
        ];
    }
}
