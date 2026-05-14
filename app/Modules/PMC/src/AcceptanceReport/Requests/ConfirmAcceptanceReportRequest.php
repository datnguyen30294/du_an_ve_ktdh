<?php

namespace App\Modules\PMC\AcceptanceReport\Requests;

use App\Common\Requests\BaseFormRequest;

class ConfirmAcceptanceReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signature_name' => ['required', 'string', 'min:2', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature_name.required' => 'Vui lòng nhập họ tên người ký xác nhận.',
            'signature_name.min' => 'Họ tên người ký phải có ít nhất 2 ký tự.',
        ];
    }
}
