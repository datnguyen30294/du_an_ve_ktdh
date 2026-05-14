<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateServiceCategoryRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->sort_order === null || $this->sort_order === '') {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('service_categories', 'code')->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục dịch vụ là bắt buộc.',
            'name.max' => 'Tên danh mục dịch vụ không được vượt quá 255 ký tự.',
            'code.required' => 'Mã danh mục dịch vụ là bắt buộc.',
            'code.max' => 'Mã danh mục dịch vụ không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã danh mục dịch vụ đã tồn tại.',
            'sort_order.min' => 'Thứ tự sắp xếp không được âm.',
        ];
    }
}
