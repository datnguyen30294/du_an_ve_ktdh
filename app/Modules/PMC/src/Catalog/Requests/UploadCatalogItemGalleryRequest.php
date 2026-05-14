<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\File;

class UploadCatalogItemGalleryRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', File::image()->max(10 * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Vui lòng chọn ít nhất 1 hình ảnh.',
            'images.max' => 'Tối đa 10 ảnh mỗi lần tải.',
            'images.*.image' => 'Tệp phải là hình ảnh (jpg, png, gif, webp...).',
            'images.*.max' => 'Kích thước mỗi ảnh không được vượt quá 10MB.',
        ];
    }
}
