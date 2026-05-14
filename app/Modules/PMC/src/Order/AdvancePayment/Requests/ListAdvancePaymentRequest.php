<?php

namespace App\Modules\PMC\Order\AdvancePayment\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ListAdvancePaymentRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['all', 'pending', 'paid'])],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->whereNull('deleted_at')],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
