<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Account\Rules\PermissionRequiresViewRule;

class CreateRoleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array', new PermissionRequiresViewRule],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên vai trò là bắt buộc.',
            'name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            'permission_ids.array' => 'Danh sách quyền phải là một mảng.',
            'permission_ids.*.exists' => 'Quyền được chọn không tồn tại.',
        ];
    }
}
