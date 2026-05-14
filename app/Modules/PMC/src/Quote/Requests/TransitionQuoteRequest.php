<?php

namespace App\Modules\PMC\Quote\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use Illuminate\Validation\Rule;

class TransitionQuoteRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(QuoteStatus::transitionTargets())],
            'note' => ['nullable', 'string', 'max:1000'],
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
