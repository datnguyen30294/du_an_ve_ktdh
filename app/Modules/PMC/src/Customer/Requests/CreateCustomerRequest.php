<?php

namespace App\Modules\PMC\Customer\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Common\Support\PhoneNormalizer;

class CreateCustomerRequest extends BaseFormRequest
{
    /**
     * Normalize phone BEFORE validation so the unique check matches stored format.
     */
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
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:pmc_customers,phone'],
            'email' => ['nullable', 'email', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
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
