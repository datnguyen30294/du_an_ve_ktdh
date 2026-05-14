<?php

namespace App\Modules\PMC\OgTicketCategory\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateOgTicketCategoryRequest extends BaseFormRequest
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
        $id = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('og_ticket_categories', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('og_ticket_categories', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'name.max' => 'Tên danh mục không được vượt quá 100 ký tự.',
            'name.unique' => 'Tên danh mục đã tồn tại.',
            'code.required' => 'Mã danh mục là bắt buộc.',
            'code.max' => 'Mã không được vượt quá 120 ký tự.',
            'code.unique' => 'Mã danh mục đã tồn tại.',
            'sort_order.min' => 'Thứ tự sắp xếp không được âm.',
        ];
    }
}
