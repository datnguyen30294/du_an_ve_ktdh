<?php

namespace App\Modules\PMC\AcceptanceReport\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\AcceptanceReport\Services\AcceptanceReportService;

class UploadSignedAcceptanceReportRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:'.implode(',', AcceptanceReportService::SIGNED_FILE_ALLOWED_MIMES),
                'max:'.(AcceptanceReportService::SIGNED_FILE_MAX_BYTES / 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn tệp biên bản đã ký.',
            'file.mimetypes' => 'Chỉ chấp nhận tệp PDF, JPG hoặc PNG.',
            'file.max' => 'Kích thước tệp tối đa 20MB.',
        ];
    }
}
