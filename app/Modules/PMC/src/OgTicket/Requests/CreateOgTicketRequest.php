<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use Illuminate\Validation\Rule;

class CreateOgTicketRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Requester (resolved to PMC Customer via findOrCreateByPhone)
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_phone' => ['required', 'string', 'max:20'],

            // Ticket content
            'subject' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:500'],
            'apartment_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'channel' => ['required', 'string', Rule::in(TicketChannel::values())],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],

            // Processing fields
            'priority' => ['required', 'string', Rule::in(OgTicketPriority::values())],
            'internal_note' => ['nullable', 'string'],
            'received_by_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'assigned_to_ids' => ['nullable', 'array'],
            'assigned_to_ids.*' => ['integer', 'exists:accounts,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:og_ticket_categories,id'],

            // Attachments
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'requester_name.required' => 'Tên người yêu cầu là bắt buộc.',
            'requester_name.max' => 'Tên người yêu cầu không được vượt quá 255 ký tự.',
            'requester_phone.required' => 'Số điện thoại là bắt buộc.',
            'requester_phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'subject.required' => 'Tiêu đề là bắt buộc.',
            'subject.max' => 'Tiêu đề không được vượt quá 500 ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá 500 ký tự.',
            'apartment_name.max' => 'Tên căn hộ không được vượt quá 255 ký tự.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng -180 đến 180.',
            'channel.required' => 'Kênh tiếp nhận là bắt buộc.',
            'channel.in' => 'Kênh tiếp nhận không hợp lệ.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'priority.required' => 'Mức ưu tiên là bắt buộc.',
            'priority.in' => 'Mức ưu tiên không hợp lệ.',
            'received_by_id.exists' => 'Người tiếp nhận không tồn tại.',
            'assigned_to_ids.*.exists' => 'Người thi công không tồn tại.',
            'category_ids.*.exists' => 'Phân loại không tồn tại.',
            'attachments.max' => 'Tối đa 10 tệp đính kèm.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Tệp phải là hình ảnh (jpg, png, gif, webp) hoặc tài liệu (pdf, doc, docx, xls, xlsx).',
        ];
    }
}
