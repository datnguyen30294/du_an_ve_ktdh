<?php

namespace App\Modules\PMC\Quote\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CheckActiveQuoteRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'og_ticket_id' => ['required', 'integer', Rule::exists('og_tickets', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'og_ticket_id.required' => 'Vui lòng chọn ticket.',
            'og_ticket_id.exists' => 'Ticket không tồn tại.',
        ];
    }
}
