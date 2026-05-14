<?php

namespace App\Modules\PMC\WorkSchedule\Requests;

use App\Common\Requests\BaseFormRequest;

class BulkUpsertWorkScheduleRequest extends BaseFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.external_ref' => ['required', 'string', 'max:255'],
            'items.*.account_code' => ['required', 'string'],
            'items.*.project_code' => ['required', 'string'],
            'items.*.shift_code' => ['required', 'string'],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Danh sách items là bắt buộc.',
            'items.min' => 'Bulk upsert cần tối thiểu 1 item.',
            'items.max' => 'Bulk upsert tối đa 500 item mỗi request.',
            'items.*.external_ref.required' => 'external_ref là bắt buộc cho bulk upsert.',
            'items.*.account_code.required' => 'account_code là bắt buộc.',
            'items.*.project_code.required' => 'project_code là bắt buộc.',
            'items.*.shift_code.required' => 'shift_code là bắt buộc.',
            'items.*.date.required' => 'date là bắt buộc.',
            'items.*.date.date_format' => 'date phải đúng định dạng YYYY-MM-DD.',
        ];
    }
}
