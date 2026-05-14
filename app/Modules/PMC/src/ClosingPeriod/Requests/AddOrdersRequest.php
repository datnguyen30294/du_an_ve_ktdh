<?php

namespace App\Modules\PMC\ClosingPeriod\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AddOrdersRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', Rule::exists('orders', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => 'Danh sách đơn hàng là bắt buộc.',
            'order_ids.array' => 'Danh sách đơn hàng phải là mảng.',
            'order_ids.min' => 'Phải chọn ít nhất 1 đơn hàng.',
            'order_ids.*.exists' => 'Đơn hàng không tồn tại.',
        ];
    }
}
