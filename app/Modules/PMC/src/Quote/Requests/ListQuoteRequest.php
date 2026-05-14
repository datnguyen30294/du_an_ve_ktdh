<?php

namespace App\Modules\PMC\Quote\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use Illuminate\Validation\Rule;

class ListQuoteRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(QuoteStatus::values())],
            'is_active' => ['nullable', 'string', 'in:true,false,1,0'],
            'og_ticket_id' => ['nullable', 'integer'],
            'sort_by' => ['nullable', 'string', 'in:created_at,total_amount'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
