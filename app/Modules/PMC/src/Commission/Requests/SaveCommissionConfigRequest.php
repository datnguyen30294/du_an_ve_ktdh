<?php

namespace App\Modules\PMC\Commission\Requests;

use App\Common\Requests\BaseFormRequest;
use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCommissionConfigRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Party rules (Level 1)
            'party_rules' => ['required', 'array', 'min:1'],
            'party_rules.*.party_type' => ['required', Rule::in(CommissionPartyType::values())],
            'party_rules.*.value_type' => ['required', Rule::in(CommissionValueType::values())],
            'party_rules.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'party_rules.*.value_fixed' => ['nullable', 'numeric', 'min:0'],

            // Dept rules (Level 2) — required when management party is present
            'dept_rules' => ['nullable', 'array'],
            'dept_rules.*.department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'dept_rules.*.sort_order' => ['required', 'integer', 'min:1'],
            'dept_rules.*.value_type' => ['required', Rule::in(CommissionValueType::values())],
            'dept_rules.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dept_rules.*.value_fixed' => ['nullable', 'numeric', 'min:0'],

            // Staff rules (Level 3)
            'dept_rules.*.staff_rules' => ['required', 'array', 'min:1'],
            'dept_rules.*.staff_rules.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')],
            'dept_rules.*.staff_rules.*.sort_order' => ['required', 'integer', 'min:1'],
            'dept_rules.*.staff_rules.*.value_type' => ['required', Rule::in(CommissionValueType::values())],
            'dept_rules.*.staff_rules.*.percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'dept_rules.*.staff_rules.*.value_fixed' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePartyTypeUnique($validator);
                $this->validatePartyConditionalRequired($validator);
                $this->validatePartyPercentSum($validator);
                $this->validateManagementRequiresDeptRules($validator);
                $this->validateConditionalRequired($validator);
                $this->validateSortOrderUnique($validator);
                $this->validateRulePercentSums($validator);
                $this->validateFixedCascade($validator);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party_rules.required' => 'Vui lòng thêm ít nhất 1 bên nhận hoa hồng.',
            'party_rules.*.party_type.required' => 'Vui lòng chọn loại bên.',
            'party_rules.*.value_type.required' => 'Vui lòng chọn loại giá trị.',
            'dept_rules.*.department_id.required' => 'Vui lòng chọn phòng ban.',
            'dept_rules.*.department_id.exists' => 'Phòng ban không tồn tại.',
            'dept_rules.*.sort_order.required' => 'Vui lòng nhập thứ tự ưu tiên.',
            'dept_rules.*.value_type.required' => 'Vui lòng chọn loại giá trị.',
            'dept_rules.*.staff_rules.required' => 'Mỗi phòng ban phải có ít nhất 1 nhân viên.',
            'dept_rules.*.staff_rules.min' => 'Mỗi phòng ban phải có ít nhất 1 nhân viên.',
            'dept_rules.*.staff_rules.*.account_id.required' => 'Vui lòng chọn nhân viên.',
            'dept_rules.*.staff_rules.*.account_id.exists' => 'Nhân viên không tồn tại.',
            'dept_rules.*.staff_rules.*.sort_order.required' => 'Vui lòng nhập thứ tự ưu tiên nhân viên.',
            'dept_rules.*.staff_rules.*.value_type.required' => 'Vui lòng chọn loại giá trị nhân viên.',
        ];
    }

    /**
     * Validate each party_type appears at most once.
     */
    private function validatePartyTypeUnique(Validator $validator): void
    {
        $types = collect($this->input('party_rules', []))->pluck('party_type')->filter();
        if ($types->count() !== $types->unique()->count()) {
            $validator->errors()->add('party_rules', 'Mỗi loại bên chỉ được cấu hình 1 lần.');
        }
    }

    /**
     * Validate percent/value_fixed required based on value_type for party rules.
     */
    private function validatePartyConditionalRequired(Validator $validator): void
    {
        foreach ($this->input('party_rules', []) as $pi => $partyRule) {
            $valueType = CommissionValueType::tryFrom($partyRule['value_type'] ?? '');
            $partyType = CommissionPartyType::tryFrom($partyRule['party_type'] ?? '');
            $label = $partyType?->label() ?? "Bên #{$pi}";

            if ($valueType?->requiresPercent() && empty($partyRule['percent']) && ($partyRule['percent'] ?? null) !== 0) {
                $validator->errors()->add("party_rules.{$pi}.percent", "{$label}: Phần trăm là bắt buộc.");
            }

            if ($valueType?->requiresFixed() && empty($partyRule['value_fixed']) && ($partyRule['value_fixed'] ?? null) !== 0) {
                $validator->errors()->add("party_rules.{$pi}.value_fixed", "{$label}: Tiền cứng là bắt buộc.");
            }
        }
    }

    /**
     * Validate party rules percent sum to 100 (including platform from constant/API).
     */
    private function validatePartyPercentSum(Validator $validator): void
    {
        $sum = (float) config('commission.platform_default_percent', 5);

        foreach ($this->input('party_rules', []) as $partyRule) {
            $valueType = CommissionValueType::tryFrom($partyRule['value_type'] ?? '');
            if ($valueType?->requiresPercent()) {
                $sum += (float) ($partyRule['percent'] ?? 0);
            }
        }

        if (abs($sum - 100) > 0.01) {
            $validator->errors()->add('party_rules', "Tổng phần trăm các bên (bao gồm Platform) phải bằng 100%. Hiện tại: {$sum}%.");
        }
    }

    /**
     * When management party is present, dept_rules must be provided.
     */
    private function validateManagementRequiresDeptRules(Validator $validator): void
    {
        $hasManagement = collect($this->input('party_rules', []))
            ->contains(fn ($r) => ($r['party_type'] ?? '') === CommissionPartyType::Management->value);

        if ($hasManagement && empty($this->input('dept_rules'))) {
            $validator->errors()->add('dept_rules', 'Khi có Ban quản lý, phải thêm ít nhất 1 phòng ban.');
        }
    }

    /**
     * Validate percent/value_fixed required based on value_type for dept and staff rules.
     */
    private function validateConditionalRequired(Validator $validator): void
    {
        foreach ($this->input('dept_rules', []) as $di => $deptRule) {
            $valueType = CommissionValueType::tryFrom($deptRule['value_type'] ?? '');

            if ($valueType?->requiresPercent() && empty($deptRule['percent']) && ($deptRule['percent'] ?? null) !== 0) {
                $validator->errors()->add("dept_rules.{$di}.percent", 'Phần trăm là bắt buộc khi loại giá trị là phần trăm hoặc cả hai.');
            }

            if ($valueType?->requiresFixed() && empty($deptRule['value_fixed']) && ($deptRule['value_fixed'] ?? null) !== 0) {
                $validator->errors()->add("dept_rules.{$di}.value_fixed", 'Tiền cứng là bắt buộc khi loại giá trị là tiền cứng hoặc cả hai.');
            }

            foreach ($deptRule['staff_rules'] ?? [] as $si => $staffRule) {
                $staffValueType = CommissionValueType::tryFrom($staffRule['value_type'] ?? '');

                if ($staffValueType?->requiresPercent() && empty($staffRule['percent']) && ($staffRule['percent'] ?? null) !== 0) {
                    $validator->errors()->add("dept_rules.{$di}.staff_rules.{$si}.percent", 'Phần trăm nhân viên là bắt buộc khi loại giá trị là phần trăm hoặc cả hai.');
                }

                if ($staffValueType?->requiresFixed() && empty($staffRule['value_fixed']) && ($staffRule['value_fixed'] ?? null) !== 0) {
                    $validator->errors()->add("dept_rules.{$di}.staff_rules.{$si}.value_fixed", 'Tiền cứng nhân viên là bắt buộc khi loại giá trị là tiền cứng hoặc cả hai.');
                }
            }
        }
    }

    /**
     * Validate sort_order uniqueness at dept level and within each dept's staff rules.
     */
    private function validateSortOrderUnique(Validator $validator): void
    {
        $deptSortOrders = collect($this->input('dept_rules', []))->pluck('sort_order')->filter();
        if ($deptSortOrders->count() !== $deptSortOrders->unique()->count()) {
            $validator->errors()->add('dept_rules', 'Thứ tự ưu tiên phòng ban phải là duy nhất.');
        }

        foreach ($this->input('dept_rules', []) as $di => $deptRule) {
            $staffSortOrders = collect($deptRule['staff_rules'] ?? [])->pluck('sort_order')->filter();
            if ($staffSortOrders->count() !== $staffSortOrders->unique()->count()) {
                $validator->errors()->add("dept_rules.{$di}.staff_rules", 'Thứ tự ưu tiên nhân viên phải là duy nhất trong phòng ban.');
            }
        }
    }

    /**
     * Validate percent sums to 100 for dept rules and within each dept's staff rules.
     */
    private function validateRulePercentSums(Validator $validator): void
    {
        $deptPercentSum = 0.0;
        $hasDeptPercent = false;

        foreach ($this->input('dept_rules', []) as $di => $deptRule) {
            $valueType = CommissionValueType::tryFrom($deptRule['value_type'] ?? '');
            if ($valueType?->requiresPercent()) {
                $hasDeptPercent = true;
                $deptPercentSum += (float) ($deptRule['percent'] ?? 0);
            }

            $staffPercentSum = 0.0;
            $hasStaffPercent = false;
            foreach ($deptRule['staff_rules'] ?? [] as $staffRule) {
                $staffValueType = CommissionValueType::tryFrom($staffRule['value_type'] ?? '');
                if ($staffValueType?->requiresPercent()) {
                    $hasStaffPercent = true;
                    $staffPercentSum += (float) ($staffRule['percent'] ?? 0);
                }
            }

            if ($hasStaffPercent && abs($staffPercentSum - 100) > 0.01) {
                $validator->errors()->add(
                    "dept_rules.{$di}.staff_rules",
                    'Tổng phần trăm nhân viên trong phòng ban phải bằng 100%.',
                );
            }
        }

        if ($hasDeptPercent && abs($deptPercentSum - 100) > 0.01) {
            $validator->errors()->add('dept_rules', 'Tổng phần trăm phòng ban (các phòng ban có loại phần trăm hoặc cả hai) phải bằng 100%.');
        }
    }

    /**
     * Validate fixed amount cascade: child total fixed <= parent fixed.
     * If parent has no fixed, children cannot use fixed.
     */
    private function validateFixedCascade(Validator $validator): void
    {
        // Bậc 1→2: BQL → dept
        $managementRule = collect($this->input('party_rules', []))
            ->first(fn ($r) => ($r['party_type'] ?? '') === CommissionPartyType::Management->value);

        if (! $managementRule) {
            return;
        }

        $managementValueType = CommissionValueType::tryFrom($managementRule['value_type'] ?? '');
        $managementHasFixed = $managementValueType?->requiresFixed() ?? false;
        $managementFixed = $managementHasFixed ? (float) ($managementRule['value_fixed'] ?? 0) : 0;

        foreach ($this->input('dept_rules', []) as $di => $deptRule) {
            $deptValueType = CommissionValueType::tryFrom($deptRule['value_type'] ?? '');
            $deptHasFixed = $deptValueType?->requiresFixed() ?? false;

            if (! $managementHasFixed && $deptHasFixed) {
                $validator->errors()->add(
                    "dept_rules.{$di}.value_fixed",
                    'Ban quản lý không có tiền cứng — phòng ban không được dùng tiền cứng.',
                );
            }

            // Bậc 2→3: dept → staff
            $deptFixed = $deptHasFixed ? (float) ($deptRule['value_fixed'] ?? 0) : 0;

            $totalStaffFixed = 0.0;
            foreach ($deptRule['staff_rules'] ?? [] as $si => $staffRule) {
                $staffValueType = CommissionValueType::tryFrom($staffRule['value_type'] ?? '');
                $staffHasFixed = $staffValueType?->requiresFixed() ?? false;

                if (! $deptHasFixed && $staffHasFixed) {
                    $validator->errors()->add(
                        "dept_rules.{$di}.staff_rules.{$si}.value_fixed",
                        'Phòng ban không có tiền cứng — nhân viên không được dùng tiền cứng.',
                    );
                }

                if ($staffHasFixed) {
                    $totalStaffFixed += (float) ($staffRule['value_fixed'] ?? 0);
                }
            }

            if ($deptHasFixed && $totalStaffFixed > $deptFixed) {
                $validator->errors()->add(
                    "dept_rules.{$di}.staff_rules",
                    "Tổng tiền cứng nhân viên ({$totalStaffFixed}) vượt quá tiền cứng phòng ban ({$deptFixed}).",
                );
            }
        }

        // Sum dept fixed vs management fixed
        if ($managementHasFixed) {
            $totalDeptFixed = 0.0;
            foreach ($this->input('dept_rules', []) as $deptRule) {
                $deptValueType = CommissionValueType::tryFrom($deptRule['value_type'] ?? '');
                if ($deptValueType?->requiresFixed()) {
                    $totalDeptFixed += (float) ($deptRule['value_fixed'] ?? 0);
                }
            }

            if ($totalDeptFixed > $managementFixed) {
                $validator->errors()->add(
                    'dept_rules',
                    "Tổng tiền cứng phòng ban ({$totalDeptFixed}) vượt quá tiền cứng Ban quản lý ({$managementFixed}).",
                );
            }
        }
    }
}
