<?php

namespace App\Modules\Platform\ExternalApi\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use Illuminate\Validation\Rule;

class CreateApiClientRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'string', Rule::exists('tenants', 'id')],
            'project_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in(ApiScope::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'organization_id.required' => 'Tổ chức là bắt buộc.',
            'organization_id.exists' => 'Tổ chức không tồn tại.',
            'project_id.required' => 'Dự án là bắt buộc.',
            'name.required' => 'Tên ứng dụng là bắt buộc.',
            'scopes.required' => 'Phải chọn ít nhất 1 quyền.',
            'scopes.*.in' => 'Quyền không hợp lệ.',
        ];
    }
}
