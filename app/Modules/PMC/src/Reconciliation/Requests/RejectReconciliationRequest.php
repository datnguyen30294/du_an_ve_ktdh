<?php

namespace App\Modules\PMC\Reconciliation\Requests;

use App\Common\Requests\BaseFormRequest;

class RejectReconciliationRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
            'reason.max' => 'Lý do không được vượt quá 500 ký tự.',
        ];
    }
}
