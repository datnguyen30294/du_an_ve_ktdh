<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;

class RegisterRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'job_title_id' => ['required', 'integer', 'exists:job_titles,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.min' => 'Mật khẩu tối thiểu 8 ký tự.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'department_ids.required' => 'Phòng ban là bắt buộc.',
            'department_ids.array' => 'Phòng ban không hợp lệ.',
            'department_ids.min' => 'Vui lòng chọn ít nhất một phòng ban.',
            'department_ids.*.exists' => 'Phòng ban không tồn tại.',
            'job_title_id.required' => 'Chức danh là bắt buộc.',
            'job_title_id.exists' => 'Chức danh không tồn tại.',
            'role_id.required' => 'Role là bắt buộc.',
            'role_id.exists' => 'Role không tồn tại.',
        ];
    }
}
