<?php

namespace App\Modules\Platform\ExternalApi\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use Illuminate\Validation\Rule;

class UpdateApiClientRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'scopes' => ['sometimes', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in(ApiScope::values())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Tên ứng dụng không quá 255 ký tự.',
            'scopes.min' => 'Phải chọn ít nhất 1 quyền.',
            'scopes.*.in' => 'Quyền không hợp lệ.',
        ];
    }
}
