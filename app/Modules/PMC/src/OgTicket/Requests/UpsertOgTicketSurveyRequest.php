<?php

namespace App\Modules\PMC\OgTicket\Requests;

use App\Common\Requests\BaseFormRequest;

class UpsertOgTicketSurveyRequest extends BaseFormRequest
{
    /** @var int */
    public const ATTACHMENT_MAX_KILOBYTES = 100 * 1024; // 100MB

    /** @var int */
    public const ATTACHMENT_MAX_FILES = 20;

    /** @var list<string> */
    public const ATTACHMENT_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/webm',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:50000'],
            'attachments' => ['sometimes', 'array', 'max:'.self::ATTACHMENT_MAX_FILES],
            'attachments.*' => [
                'file',
                'mimetypes:'.implode(',', self::ATTACHMENT_ALLOWED_MIMES),
                'max:'.self::ATTACHMENT_MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.max' => 'Ghi chú quá dài.',
            'attachments.max' => 'Mỗi lần chỉ được tải lên tối đa '.self::ATTACHMENT_MAX_FILES.' tệp.',
            'attachments.*.file' => 'Tệp đính kèm không hợp lệ.',
            'attachments.*.mimetypes' => 'Chỉ chấp nhận ảnh, video, PDF hoặc tài liệu Word/Excel/Text.',
            'attachments.*.max' => 'Mỗi tệp tối đa 100MB.',
        ];
    }
}
