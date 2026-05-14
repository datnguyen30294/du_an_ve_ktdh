<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use Illuminate\Validation\Rule;

class UpdateOgTicketRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Processing fields (status managed by lifecycle flow, not manual edit)
            'priority' => ['required', 'string', Rule::in(OgTicketPriority::values())],
            'internal_note' => ['nullable', 'string'],
            'received_by_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'assigned_to_ids' => ['nullable', 'array'],
            'assigned_to_ids.*' => ['integer', 'exists:accounts,id'],
            'sla_quote_due_at' => ['nullable', 'date'],
            'sla_completion_due_at' => ['nullable', 'date'],

            // Editable ticket info
            // NOTE: requester_name/requester_phone là snapshot tại thời điểm tạo ticket,
            // không cho sửa. Muốn cập nhật thông tin liên hệ → sửa ở module Khách hàng
            // (Customer) theo customer_id của ticket.
            'subject' => ['sometimes', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'apartment_name' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],

            // Attachments
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx'],
            'delete_attachment_ids' => ['nullable', 'array'],
            'delete_attachment_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'priority.required' => 'Mức ưu tiên là bắt buộc.',
            'priority.in' => 'Mức ưu tiên không hợp lệ.',
            'received_by_id.exists' => 'Người tiếp nhận không tồn tại.',
            'assigned_to_ids.*.exists' => 'Người thi công không tồn tại.',
            'sla_quote_due_at.date' => 'Hạn SLA báo giá không hợp lệ.',
            'sla_completion_due_at.date' => 'Hạn SLA hoàn thành không hợp lệ.',
            'subject.max' => 'Tiêu đề không được vượt quá 500 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'attachments.max' => 'Tối đa 10 tệp đính kèm.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Tệp phải là hình ảnh hoặc tài liệu (jpg, png, gif, webp, pdf, doc, docx, xls, xlsx).',
            'project_id.exists' => 'Dự án không tồn tại.',
        ];
    }
}
