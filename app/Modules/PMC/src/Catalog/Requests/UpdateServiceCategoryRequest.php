<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use Illuminate\Validation\Rule;

class UpdateServiceCategoryRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('sort_order') && ($this->sort_order === null || $this->sort_order === '')) {
            $this->merge(['sort_order' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('service_categories', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('id')),
            ],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::enum(CatalogStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục dịch vụ là bắt buộc.',
            'code.required' => 'Mã danh mục dịch vụ là bắt buộc.',
            'code.unique' => 'Mã danh mục dịch vụ đã tồn tại.',
            'sort_order.min' => 'Thứ tự sắp xếp không được âm.',
            'status.enum' => 'Trạng thái không hợp lệ.',
        ];
    }
}
