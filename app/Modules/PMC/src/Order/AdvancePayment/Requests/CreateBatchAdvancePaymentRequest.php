<?php

namespace App\Modules\PMC\Order\AdvancePayment\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateBatchAdvancePaymentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_line_ids' => ['required', 'array', 'min:1'],
            'order_line_ids.*' => ['integer', Rule::exists('order_lines', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_line_ids.required' => 'Cần chọn ít nhất 1 dòng để hoàn tiền ứng.',
            'order_line_ids.min' => 'Cần chọn ít nhất 1 dòng để hoàn tiền ứng.',
            'order_line_ids.*.exists' => 'Dòng đơn hàng không tồn tại.',
        ];
    }
}
