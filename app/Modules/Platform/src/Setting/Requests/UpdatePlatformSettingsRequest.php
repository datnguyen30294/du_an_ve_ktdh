<?php

namespace App\Modules\Platform\Setting\Requests;

use App\Common\Requests\BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Validator as ValidatorFacade;

class UpdatePlatformSettingsRequest extends BaseFormRequest
{
    /**
     * Per-group, per-key validation rules.
     *
     * @var array<string, array<string, array<int, mixed>>>
     */
    private const GROUP_KEY_RULES = [
        'bank_account' => [
            'bank_bin' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'account_holder' => ['nullable', 'string', 'max:100'],
        ],
    ];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var string|null $group */
            $group = $this->route('group');
            $keyRules = self::GROUP_KEY_RULES[$group] ?? [];

            foreach ($this->input('settings', []) as $index => $item) {
                $key = $item['key'] ?? null;
                $rules = $keyRules[$key] ?? ['nullable', 'string', 'max:10000'];

                $itemValidator = ValidatorFacade::make(
                    ['value' => $item['value'] ?? null],
                    ['value' => $rules],
                );

                if ($itemValidator->fails()) {
                    foreach ($itemValidator->errors()->all() as $message) {
                        $validator->errors()->add("settings.{$index}.value", $message);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'settings.required' => 'Danh sách cài đặt không được để trống.',
            'settings.array' => 'Danh sách cài đặt phải là mảng.',
            'settings.min' => 'Cần ít nhất một cài đặt.',
            'settings.*.key.required' => 'Key cài đặt không được để trống.',
            'settings.*.key.string' => 'Key cài đặt phải là chuỗi.',
        ];
    }
}
