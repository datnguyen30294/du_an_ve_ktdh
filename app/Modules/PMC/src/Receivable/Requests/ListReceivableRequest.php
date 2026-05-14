<?php

namespace App\Modules\PMC\Receivable\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use Illuminate\Validation\Rule;

class ListReceivableRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ReceivableStatus::values())],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'sort_by' => ['nullable', 'string', 'in:created_at,amount,paid_amount,due_date'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'Từ khoá tìm kiếm không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'project_id.integer' => 'Dự án không hợp lệ.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }
}
