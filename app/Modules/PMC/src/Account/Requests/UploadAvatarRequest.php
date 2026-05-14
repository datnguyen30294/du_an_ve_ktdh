<?php

namespace App\Modules\PMC\Account\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\File;

class UploadAvatarRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Vui lòng chọn ảnh đại diện.',
            'avatar.image' => 'Tệp phải là hình ảnh (jpg, png, gif, webp...).',
            'avatar.max' => 'Kích thước ảnh không được vượt quá 10MB.',
        ];
    }
}
