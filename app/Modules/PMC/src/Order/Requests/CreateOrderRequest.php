<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quote_id' => ['required', 'integer', Rule::exists('quotes', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quote_id.required' => 'Vui lòng chọn báo giá.',
            'quote_id.exists' => 'Báo giá không tồn tại.',
            'note.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
