<?php

namespace App\Modules\Platform\Ticket\Requests;

use App\Common\Requests\BaseFormRequest;

class SubmitWarrantyRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'description' => ['required', 'string', 'max:5000'],
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
            'subject.required' => 'Tiêu đề là bắt buộc.',
            'subject.max' => 'Tiêu đề không được vượt quá 500 ký tự.',
            'description.required' => 'Mô tả là bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự.',
            'attachments.max' => 'Tối đa 10 tệp đính kèm.',
            'attachments.*.max' => 'Mỗi tệp không được vượt quá 10MB.',
            'attachments.*.mimes' => 'Tệp phải là hình ảnh (jpg, png, gif, webp) hoặc tài liệu (pdf, doc, docx, xls, xlsx).',
        ];
    }
}
