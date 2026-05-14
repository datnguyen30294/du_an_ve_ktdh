<?php

namespace App\Modules\PMC\Project\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use Illuminate\Validation\Rule;

class CreateProjectRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in(ProjectStatus::values())],
            'bqt_bank_bin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'bqt_bank_name' => ['nullable', 'string', 'max:100'],
            'bqt_account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'bqt_account_holder' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Mã dự án là bắt buộc.',
            'code.max' => 'Mã dự án không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã dự án đã tồn tại.',
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
