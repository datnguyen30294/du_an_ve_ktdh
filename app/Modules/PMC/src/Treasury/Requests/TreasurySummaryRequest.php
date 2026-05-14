<?php

namespace App\Modules\PMC\Treasury\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TreasurySummaryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')->whereNull('deleted_at')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_account_id.exists' => 'Tài khoản quỹ không tồn tại.',
            'date_from.date' => 'Ngày bắt đầu không hợp lệ.',
            'date_to.date' => 'Ngày kết thúc không hợp lệ.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
        ];
    }
}
