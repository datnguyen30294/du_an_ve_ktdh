<?php

namespace App\Modules\PMC\Receivable\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use Illuminate\Validation\Rule;

class CreateRefundRequest extends BaseFormRequest
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
            'amount.required' => 'Số tiền hoàn trả là bắt buộc.',
            'amount.numeric' => 'Số tiền hoàn trả phải là số.',
            'amount.min' => 'Số tiền hoàn trả phải lớn hơn 0.',
            'payment_method.required' => 'Phương thức hoàn trả là bắt buộc.',
            'payment_method.in' => 'Phương thức hoàn trả không hợp lệ.',
            'note.max' => 'Ghi chú không được vượt quá 500 ký tự.',
            'paid_at.required' => 'Ngày hoàn trả là bắt buộc.',
            'paid_at.date' => 'Ngày hoàn trả không hợp lệ.',
        ];
    }
}
