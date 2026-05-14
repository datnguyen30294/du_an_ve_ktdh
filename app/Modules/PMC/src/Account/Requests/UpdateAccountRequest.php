<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Account\Enums\Gender;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', Rule::in(Gender::values())],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'job_title_id' => ['required', 'integer', 'exists:job_titles,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
            'is_active' => ['nullable', 'boolean'],
            'capability_rating' => ['nullable', 'integer', 'min:1', 'max:10'],
            'bank_bin' => ['nullable', 'string', 'max:10'],
            'bank_label' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Require all-or-nothing for bank info. If any bank field is provided,
     * bin + account number + account name are all required.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $fields = ['bank_bin', 'bank_account_number', 'bank_account_name'];
            $hasAny = collect($fields)->contains(fn ($f) => filled($this->input($f)));

            if (! $hasAny) {
                return;
            }

            foreach ($fields as $field) {
                if (blank($this->input($field))) {
                    $v->errors()->add($field, 'Vui lòng nhập đầy đủ thông tin ngân hàng (BIN, số TK, tên chủ TK).');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Họ tên là bắt buộc.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'department_ids.required' => 'Phòng ban là bắt buộc.',
            'department_ids.array' => 'Phòng ban không hợp lệ.',
            'department_ids.min' => 'Vui lòng chọn ít nhất một phòng ban.',
            'department_ids.*.exists' => 'Phòng ban không tồn tại.',
            'job_title_id.required' => 'Chức danh là bắt buộc.',
            'job_title_id.exists' => 'Chức danh không tồn tại.',
            'role_id.required' => 'Role là bắt buộc.',
            'role_id.exists' => 'Role không tồn tại.',
            'project_ids.*.exists' => 'Dự án không tồn tại.',
            'capability_rating.integer' => 'Điểm năng lực phải là số nguyên.',
            'capability_rating.min' => 'Điểm năng lực tối thiểu là 1.',
            'capability_rating.max' => 'Điểm năng lực tối đa là 10.',
        ];
    }
}
