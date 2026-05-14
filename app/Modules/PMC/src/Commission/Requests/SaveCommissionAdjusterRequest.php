<?php

namespace App\Modules\PMC\Commission\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveCommissionAdjusterRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['required', 'integer', Rule::exists('accounts', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_ids.required' => 'Vui lòng chọn ít nhất 1 người điều chỉnh.',
            'account_ids.*.exists' => 'Nhân viên không tồn tại.',
        ];
    }
}
