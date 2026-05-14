<?php

namespace App\Modules\PMC\Order\AdvancePayment\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateAdvancePaymentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_line_id' => ['required', 'integer', Rule::exists('order_lines', 'id')],
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
            'order_line_id.required' => 'Cần chọn dòng đơn hàng để hoàn tiền ứng.',
            'order_line_id.exists' => 'Dòng đơn hàng không tồn tại.',
        ];
    }
}
