<?php

namespace App\Modules\PMC\Catalog\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CreateCatalogSupplierRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('catalog_suppliers', 'code')->whereNull('deleted_at')],
            'contact' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên nhà cung cấp là bắt buộc.',
            'name.max' => 'Tên nhà cung cấp không được vượt quá 255 ký tự.',
            'code.required' => 'Mã nhà cung cấp là bắt buộc.',
            'code.max' => 'Mã nhà cung cấp không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã nhà cung cấp đã tồn tại.',
            'email.email' => 'Email không hợp lệ.',
            'commission_rate.min' => 'Tỷ lệ hoa hồng không được âm.',
            'commission_rate.max' => 'Tỷ lệ hoa hồng không được vượt quá 100%.',
        ];
    }
}
