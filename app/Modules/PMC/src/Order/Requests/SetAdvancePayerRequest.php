<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SetAdvancePayerRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'advance_payer_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'advance_payer_id.integer' => 'ID người ứng tiền không hợp lệ.',
            'advance_payer_id.exists' => 'Người ứng tiền không tồn tại hoặc đã ngừng hoạt động.',
        ];
    }
}
