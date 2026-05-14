<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;

class UpdateOrderRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
