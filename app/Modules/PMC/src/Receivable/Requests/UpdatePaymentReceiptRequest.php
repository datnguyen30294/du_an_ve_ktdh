<?php

namespace App\Modules\PMC\Receivable\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class UpdatePaymentReceiptRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', Rule::in(PaymentMethod::values())],
            'note' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Số tiền thu là bắt buộc.',
            'amount.numeric' => 'Số tiền thu phải là số.',
            'amount.min' => 'Số tiền thu phải lớn hơn 0.',
            'payment_method.required' => 'Phương thức thanh toán là bắt buộc.',
            'payment_method.in' => 'Phương thức thanh toán không hợp lệ.',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
            'paid_at.required' => 'Ngày thu tiền là bắt buộc.',
            'paid_at.date' => 'Ngày thu tiền không hợp lệ.',
        ];
    }
}
