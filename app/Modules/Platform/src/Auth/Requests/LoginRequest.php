<?php

namespace App\Modules\Platform\Auth\Requests;

use App\Common\Requests\BaseFormRequest;

class LoginRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /** @example admin@platform.com */
            'email' => ['required', 'string', 'email'],
            /** @example password */
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Mật khẩu là bắt buộc.',
        ];
    }
}
