<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Order\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class TransitionOrderRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderStatus::transitionTargets())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
