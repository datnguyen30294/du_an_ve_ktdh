<?php

namespace App\Modules\PMC\Receivable\Requests;

use App\Common\Requests\BaseFormRequest;

class WriteOffReceivableRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
        ];
    }
}
