<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use Illuminate\Validation\Rule;

class TransitionOgTicketRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_status' => ['required', 'string', Rule::in(OgTicketStatus::values())],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_status.required' => 'Trạng thái đích là bắt buộc.',
            'target_status.in' => 'Trạng thái đích không hợp lệ.',
        ];
    }
}
