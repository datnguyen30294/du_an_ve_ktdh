<?php

namespace App\Modules\PMC\Project\Requests;

use App\Common\Requests\BaseFormRequest;

class SyncMembersRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_ids' => ['present', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_ids.present' => 'Danh sách nhân viên là bắt buộc.',
            'account_ids.array' => 'Danh sách nhân viên phải là mảng.',
            'account_ids.*.integer' => 'ID nhân viên không hợp lệ.',
            'account_ids.*.exists' => 'Nhân viên không tồn tại.',
        ];
    }
}
