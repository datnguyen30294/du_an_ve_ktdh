<?php

namespace App\Modules\PMC\Customer\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Common\Support\PhoneNormalizer;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => PhoneNormalizer::normalize($this->input('phone')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('pmc_customers', 'phone')->ignore($id)->whereNull('deleted_at'),
            ],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên là bắt buộc.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'phone.required' => 'Số điện thoại là bắt buộc.',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'phone.unique' => 'Số điện thoại đã tồn tại.',
            'email.email' => 'Email không hợp lệ.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'note.max' => 'Ghi chú không được vượt quá 2000 ký tự.',
        ];
    }
}
