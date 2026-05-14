<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;

class ClaimTicketRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ticket_id.required' => 'Ticket là bắt buộc.',
            'ticket_id.integer' => 'Ticket ID phải là số nguyên.',
        ];
    }
}
