<?php

namespace App\Modules\PMC\Treasury\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use Illuminate\Validation\Rule;

class ListCashTransactionRequest extends BaseFormRequest
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
            'direction' => ['nullable', 'string', Rule::in(CashTransactionDirection::values())],
            'category' => ['nullable', 'string', Rule::in(CashTransactionCategory::values())],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')],
            'search' => ['nullable', 'string', 'max:255'],
            'include_deleted' => ['nullable', 'string', Rule::in(['none', 'manual', 'auto', 'all'])],
            'sort_by' => ['nullable', 'string', Rule::in(['transaction_date', 'amount', 'created_at', 'code'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'direction.in' => 'Hướng giao dịch không hợp lệ.',
            'category.in' => 'Danh mục không hợp lệ.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
            'search.max' => 'Từ khóa tối đa 255 ký tự.',
            'include_deleted.in' => 'Giá trị include_deleted không hợp lệ.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa 100.',
        ];
    }
}
