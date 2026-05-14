<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;

class UpdateOrderLinePricesRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_price.required' => 'Đơn giá bán là bắt buộc.',
            'unit_price.min' => 'Đơn giá bán không được âm.',
            'purchase_price.min' => 'Giá nhập không được âm.',
        ];
    }
}
