<?php

namespace App\Modules\PMC\Treasury\Requests;

use App\Common\Requests\BaseFormRequest;

class DeleteCashTransactionRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do xóa.',
            'reason.string' => 'Lý do xóa không hợp lệ.',
            'reason.min' => 'Lý do xóa tối thiểu 5 ký tự.',
            'reason.max' => 'Lý do xóa tối đa 500 ký tự.',
        ];
    }
}
