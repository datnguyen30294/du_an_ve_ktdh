<?php

namespace App\Modules\PMC\AcceptanceReport\Requests;

use App\Common\Requests\BaseFormRequest;

class UpdateAcceptanceReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content_html' => ['sometimes', 'string', 'max:1000000'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
