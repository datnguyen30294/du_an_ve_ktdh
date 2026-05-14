<?php

namespace App\Modules\PMC\Order\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Order\Enums\CommissionOverrideRecipientType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCommissionOverrideRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'overrides' => ['required', 'array', 'min:1'],
            'overrides.*.recipient_type' => ['required', Rule::in(CommissionOverrideRecipientType::values())],
            'overrides.*.account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'overrides.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateRecipientUniqueness($validator);
                $this->validateStaffRequiresAccount($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'overrides.required' => 'Vui lòng thêm ít nhất 1 người nhận.',
            'overrides.min' => 'Vui lòng thêm ít nhất 1 người nhận.',
            'overrides.*.recipient_type.required' => 'Vui lòng chọn loại người nhận.',
            'overrides.*.account_id.exists' => 'Tài khoản không tồn tại.',
            'overrides.*.amount.required' => 'Vui lòng nhập số tiền.',
            'overrides.*.amount.min' => 'Số tiền phải >= 0.',
        ];
    }

    /**
     * Validate operating_company/board_of_directors appear at most once, no duplicate account_ids for staff.
     */
    private function validateRecipientUniqueness(Validator $validator): void
    {
        $overrides = $this->input('overrides', []);

        // Check entity types appear at most once
        foreach ([CommissionOverrideRecipientType::OperatingCompany, CommissionOverrideRecipientType::BoardOfDirectors] as $type) {
            $count = collect($overrides)->where('recipient_type', $type->value)->count();
            if ($count > 1) {
                $validator->errors()->add('overrides', "{$type->label()} chỉ được xuất hiện tối đa 1 lần.");
            }
        }

        // Check no duplicate account_ids for staff
        $staffAccountIds = collect($overrides)
            ->where('recipient_type', CommissionOverrideRecipientType::Staff->value)
            ->pluck('account_id')
            ->filter();

        if ($staffAccountIds->count() !== $staffAccountIds->unique()->count()) {
            $validator->errors()->add('overrides', 'Không được trùng nhân viên nhận tiền.');
        }
    }

    /**
     * Validate staff type must have account_id.
     */
    private function validateStaffRequiresAccount(Validator $validator): void
    {
        foreach ($this->input('overrides', []) as $i => $override) {
            if (($override['recipient_type'] ?? '') === CommissionOverrideRecipientType::Staff->value
                && empty($override['account_id'])) {
                $validator->errors()->add("overrides.{$i}.account_id", 'Nhân viên phải có tài khoản.');
            }
        }
    }
}
