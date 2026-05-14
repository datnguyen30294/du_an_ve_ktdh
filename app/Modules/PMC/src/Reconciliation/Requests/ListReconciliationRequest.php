<?php

namespace App\Modules\PMC\Reconciliation\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use Illuminate\Validation\Rule;

class ListReconciliationRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ReconciliationStatus::values())],
            'source' => ['nullable', 'string', Rule::in(['receivable', 'manual_cash'])],
            'receivable_id' => ['nullable', 'integer', Rule::exists('receivables', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'type' => ['nullable', 'string', Rule::in(PaymentReceiptType::values())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'paid_at', 'amount'])],
            'sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
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
            'receivable_id.integer' => 'Khoản công nợ không hợp lệ.',
            'receivable_id.exists' => 'Khoản công nợ không tồn tại.',
            'project_id.integer' => 'Dự án không hợp lệ.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'type.in' => 'Loại phiếu thu không hợp lệ.',
            'date_from.date' => 'Ngày bắt đầu không hợp lệ.',
            'date_to.date' => 'Ngày kết thúc không hợp lệ.',
            'date_to.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'sort_by.in' => 'Trường sắp xếp không hợp lệ.',
            'sort_direction.in' => 'Hướng sắp xếp không hợp lệ.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang tối thiểu là 1.',
            'per_page.max' => 'Số bản ghi mỗi trang tối đa là 100.',
        ];
    }
}
