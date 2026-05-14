<?php

namespace App\Modules\PMC\Treasury\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ManualTopupRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_account_id' => ['required', 'integer', Rule::exists('cash_accounts', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_account_id.required' => 'Vui lòng chọn tài khoản quỹ.',
            'cash_account_id.integer' => 'Tài khoản quỹ không hợp lệ.',
            'cash_account_id.exists' => 'Tài khoản quỹ không tồn tại.',
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.numeric' => 'Số tiền phải là số.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'transaction_date.required' => 'Vui lòng chọn ngày giao dịch.',
            'transaction_date.date' => 'Ngày giao dịch không hợp lệ.',
            'transaction_date.before_or_equal' => 'Ngày giao dịch không được ở tương lai.',
            'note.max' => 'Ghi chú tối đa 1000 ký tự.',
        ];
    }
}
