<?php

namespace App\Modules\Platform\Ticket\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SubmitQuoteDecisionRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000', 'required_if:action,reject'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Vui lòng chọn hành động.',
            'action.in' => 'Hành động không hợp lệ.',
            'reason.required_if' => 'Vui lòng nhập lý do từ chối.',
            'reason.min' => 'Lý do từ chối phải có ít nhất 5 ký tự.',
            'reason.max' => 'Lý do không được vượt quá 1000 ký tự.',
        ];
    }
}
