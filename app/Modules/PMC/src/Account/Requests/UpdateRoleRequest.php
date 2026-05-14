<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Rules\PermissionRequiresViewRule;
use Closure;

class UpdateRoleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean', $this->preventDeactivationWhenAccountsExist()],
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

    private function preventDeactivationWhenAccountsExist(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value) {
                return;
            }

            $roleId = $this->route('id');
            $accountCount = Account::where('role_id', $roleId)->count();

            if ($accountCount > 0) {
                $fail("Không thể tắt: còn {$accountCount} tài khoản đang dùng vai trò này.");
            }
        };
    }
}
