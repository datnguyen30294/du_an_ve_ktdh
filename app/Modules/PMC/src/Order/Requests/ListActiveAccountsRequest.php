<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;

class ListActiveAccountsRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
