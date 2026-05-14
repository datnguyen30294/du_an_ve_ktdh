<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\File;

class UploadCatalogItemImageRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Vui lòng chọn hình ảnh.',
            'image.image' => 'Tệp phải là hình ảnh (jpg, png, gif, webp...).',
            'image.max' => 'Kích thước ảnh không được vượt quá 10MB.',
        ];
    }
}
