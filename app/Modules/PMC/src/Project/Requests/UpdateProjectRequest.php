<?php

namespace App\Modules\PMC\Project\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'required', 'string', Rule::in(ProjectStatus::values())],
            'bqt_bank_bin' => ['sometimes', 'nullable', 'string', 'regex:/^\d{6}$/'],
            'bqt_bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'bqt_account_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'bqt_account_holder' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên dự án là bắt buộc.',
            'name.max' => 'Tên dự án không được vượt quá 255 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'status.required' => 'Trạng thái là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
